using System.Security.Principal;
using System.Text.Json;

namespace BackupSecretHelper;

public enum WorkerOperation
{
    Identity,
    Protect,
    Unprotect,
    HardenDirectory,
    HardenFile,
}

public sealed record WorkerRequest(
    WorkerOperation Operation,
    string? ExpectedSid = null,
    byte[]? Data = null,
    byte[]? Entropy = null,
    string? Path = null);

public sealed class WorkerProtocolException(string code) : Exception("worker request rejected")
{
    public string Code { get; } = code;
}

public static class ProtocolParser
{
    private const int MaxDataBytes = 8192;

    public static WorkerRequest Parse(string operation, string json)
    {
        WorkerOperation typedOperation = operation switch
        {
            "identity" => WorkerOperation.Identity,
            "protect" => WorkerOperation.Protect,
            "unprotect" => WorkerOperation.Unprotect,
            "harden-directory" => WorkerOperation.HardenDirectory,
            "harden-file" => WorkerOperation.HardenFile,
            _ => throw Invalid(),
        };

        try
        {
            using JsonDocument document = JsonDocument.Parse(json, new JsonDocumentOptions
            {
                AllowTrailingCommas = false,
                CommentHandling = JsonCommentHandling.Disallow,
                MaxDepth = 8,
            });
            JsonElement root = document.RootElement;
            if (root.ValueKind != JsonValueKind.Object)
            {
                throw Invalid();
            }

            return typedOperation switch
            {
                WorkerOperation.Identity => ParseIdentity(root),
                WorkerOperation.Protect or WorkerOperation.Unprotect => ParseCrypto(typedOperation, root),
                WorkerOperation.HardenDirectory or WorkerOperation.HardenFile => ParseAcl(typedOperation, root),
                _ => throw Invalid(),
            };
        }
        catch (WorkerProtocolException)
        {
            throw;
        }
        catch (Exception exception) when (exception is JsonException or FormatException or ArgumentException)
        {
            throw Invalid();
        }
    }

    private static WorkerRequest ParseIdentity(JsonElement root)
    {
        RequireExactProperties(root, []);
        return new WorkerRequest(WorkerOperation.Identity);
    }

    private static WorkerRequest ParseCrypto(WorkerOperation operation, JsonElement root)
    {
        RequireExactProperties(root, ["data_b64", "entropy_b64", "expected_sid"]);
        string sid = RequiredString(root, "expected_sid", 184);
        ValidateSid(sid);
        byte[] data = DecodeBase64(RequiredString(root, "data_b64", 12000), MaxDataBytes);
        byte[] entropy = DecodeBase64(RequiredString(root, "entropy_b64", 128), 32);
        if (data.Length == 0 || entropy.Length != 32)
        {
            throw Invalid();
        }
        return new WorkerRequest(operation, sid, data, entropy);
    }

    private static WorkerRequest ParseAcl(WorkerOperation operation, JsonElement root)
    {
        RequireExactProperties(root, ["expected_sid", "path"]);
        string sid = RequiredString(root, "expected_sid", 184);
        ValidateSid(sid);
        string path = RequiredString(root, "path", 1024);
        if ((operation == WorkerOperation.HardenDirectory && !AclPathPolicy.IsApprovedDirectory(path))
            || (operation == WorkerOperation.HardenFile && !AclPathPolicy.IsApprovedFile(path)))
        {
            throw Invalid();
        }
        return new WorkerRequest(operation, sid, Path: path);
    }

    private static void RequireExactProperties(JsonElement root, IReadOnlyCollection<string> expected)
    {
        var actual = new List<string>();
        foreach (JsonProperty property in root.EnumerateObject())
        {
            if (actual.Contains(property.Name, StringComparer.Ordinal))
            {
                throw Invalid();
            }
            actual.Add(property.Name);
        }
        actual.Sort(StringComparer.Ordinal);
        string[] sortedExpected = expected.OrderBy(static name => name, StringComparer.Ordinal).ToArray();
        if (!actual.SequenceEqual(sortedExpected, StringComparer.Ordinal))
        {
            throw Invalid();
        }
    }

    private static string RequiredString(JsonElement root, string name, int maxLength)
    {
        JsonElement value = root.GetProperty(name);
        if (value.ValueKind != JsonValueKind.String)
        {
            throw Invalid();
        }
        string? text = value.GetString();
        if (string.IsNullOrEmpty(text) || text.Length > maxLength || text.Any(char.IsControl))
        {
            throw Invalid();
        }
        return text;
    }

    private static byte[] DecodeBase64(string value, int maximumBytes)
    {
        byte[] decoded = Convert.FromBase64String(value);
        if (decoded.Length > maximumBytes)
        {
            throw Invalid();
        }
        return decoded;
    }

    private static void ValidateSid(string value)
    {
        _ = new SecurityIdentifier(value);
        if (!value.StartsWith("S-", StringComparison.Ordinal) || value.Length > 184)
        {
            throw Invalid();
        }
    }

    private static WorkerProtocolException Invalid() => new("worker_input_invalid");
}
