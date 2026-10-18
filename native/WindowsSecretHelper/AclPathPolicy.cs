using System.Text.RegularExpressions;

namespace BackupSecretHelper;

public static partial class AclPathPolicy
{
    public const string SecretDirectory = @"C:\ProgramData\BackupPhpMyAdmin\SecretProvider\secrets";

    public static bool IsApprovedDirectory(string path)
    {
        return TryCanonicalize(path, out string? full)
            && string.Equals(full, SecretDirectory, StringComparison.OrdinalIgnoreCase);
    }

    public static bool IsApprovedFile(string path)
    {
        if (!TryCanonicalize(path, out string? full)
            || !string.Equals(Path.GetDirectoryName(full), SecretDirectory, StringComparison.OrdinalIgnoreCase))
        {
            return false;
        }

        string? name = Path.GetFileName(full);
        return name is not null && (name is "backup-reader.dpapi.json" or "restore-runner.dpapi.json"
            || TemporaryFileName().IsMatch(name));
    }

    public static bool HasNoReparsePoint(string path)
    {
        string? current = Path.GetPathRoot(path);
        foreach (string segment in Path.GetFullPath(path)[current!.Length..].Split(Path.DirectorySeparatorChar, StringSplitOptions.RemoveEmptyEntries))
        {
            current = Path.Combine(current, segment);
            if (!Directory.Exists(current) && !File.Exists(current))
            {
                continue;
            }
            if ((File.GetAttributes(current) & FileAttributes.ReparsePoint) != 0)
            {
                return false;
            }
        }
        return true;
    }

    private static bool TryCanonicalize(string path, out string? full)
    {
        full = null;
        try
        {
            if (string.IsNullOrWhiteSpace(path) || !Path.IsPathFullyQualified(path))
            {
                return false;
            }
            full = Path.TrimEndingDirectorySeparator(Path.GetFullPath(path));
            return !path.Contains(Path.AltDirectorySeparatorChar + ".." + Path.AltDirectorySeparatorChar, StringComparison.Ordinal)
                && !path.Contains(Path.DirectorySeparatorChar + ".." + Path.DirectorySeparatorChar, StringComparison.Ordinal);
        }
        catch (Exception exception) when (exception is ArgumentException or NotSupportedException or PathTooLongException)
        {
            return false;
        }
    }

    [GeneratedRegex(@"^\.(?:backup-reader|restore-runner)\.dpapi\.json\.[0-9a-f]{24}\.tmp$", RegexOptions.CultureInvariant)]
    private static partial Regex TemporaryFileName();
}
