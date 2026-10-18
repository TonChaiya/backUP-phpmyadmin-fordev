using System.Security.AccessControl;
using System.Security.Cryptography;
using System.Security.Principal;

namespace BackupSecretHelper;

public static class WorkerServices
{
    public static Dictionary<string, object> Execute(WorkerRequest request)
    {
        using WindowsIdentity identity = WindowsIdentity.GetCurrent(TokenAccessLevels.Query);
        string? account = identity.Name;
        string? sidText = identity.User?.Value;
        if (string.IsNullOrWhiteSpace(account) || string.IsNullOrWhiteSpace(sidText))
        {
            throw new SafeWorkerException("windows_identity_unavailable");
        }

        if (request.Operation == WorkerOperation.Identity)
        {
            return new() { ["ok"] = true, ["account"] = account, ["sid"] = sidText };
        }

        if (!string.Equals(request.ExpectedSid, sidText, StringComparison.Ordinal))
        {
            throw new SafeWorkerException("windows_identity_mismatch");
        }

        var sid = new SecurityIdentifier(sidText);
        return request.Operation switch
        {
            WorkerOperation.Protect => BinaryResult("ciphertext_b64", ProtectedData.Protect(request.Data!, request.Entropy, DataProtectionScope.CurrentUser)),
            WorkerOperation.Unprotect => Unprotect(request),
            WorkerOperation.HardenDirectory => HardenDirectory(request.Path!, sid),
            WorkerOperation.HardenFile => HardenFile(request.Path!, sid),
            _ => throw new SafeWorkerException("worker_failed"),
        };
    }

    private static Dictionary<string, object> Unprotect(WorkerRequest request)
    {
        try
        {
            return BinaryResult("plaintext_b64", ProtectedData.Unprotect(request.Data!, request.Entropy, DataProtectionScope.CurrentUser));
        }
        catch (CryptographicException)
        {
            throw new SafeWorkerException("secret_decryption_failed");
        }
    }

    private static Dictionary<string, object> HardenDirectory(string path, SecurityIdentifier sid)
    {
        try
        {
            if (!AclPathPolicy.IsApprovedDirectory(path) || !Directory.Exists(path) || !AclPathPolicy.HasNoReparsePoint(path))
            {
                throw new SafeWorkerException("acl_hardening_failed");
            }
            var info = new DirectoryInfo(path);
            DirectorySecurity security = info.GetAccessControl(AccessControlSections.All);
            RestrictToSid(security, sid, InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit);
            info.SetAccessControl(security);
            return new() { ["ok"] = true };
        }
        catch (SafeWorkerException) { throw; }
        catch { throw new SafeWorkerException("acl_hardening_failed"); }
    }

    private static Dictionary<string, object> HardenFile(string path, SecurityIdentifier sid)
    {
        try
        {
            if (!AclPathPolicy.IsApprovedFile(path) || !File.Exists(path) || !AclPathPolicy.HasNoReparsePoint(path))
            {
                throw new SafeWorkerException("acl_hardening_failed");
            }
            var info = new FileInfo(path);
            FileSecurity security = info.GetAccessControl(AccessControlSections.All);
            RestrictToSid(security, sid, InheritanceFlags.None);
            info.SetAccessControl(security);
            return new() { ["ok"] = true };
        }
        catch (SafeWorkerException) { throw; }
        catch { throw new SafeWorkerException("acl_hardening_failed"); }
    }

    private static Dictionary<string, object> BinaryResult(string key, byte[] value)
        => new() { ["ok"] = true, [key] = Convert.ToBase64String(value) };

    private static void RestrictToSid(FileSystemSecurity security, SecurityIdentifier sid, InheritanceFlags inheritance)
    {
        security.SetAccessRuleProtection(true, false);
        foreach (FileSystemAccessRule rule in security.GetAccessRules(true, false, typeof(SecurityIdentifier)))
        {
            security.RemoveAccessRuleSpecific(rule);
        }
        security.SetOwner(sid);
        security.AddAccessRule(new FileSystemAccessRule(
            sid,
            FileSystemRights.FullControl,
            inheritance,
            PropagationFlags.None,
            AccessControlType.Allow));
    }
}

public sealed class SafeWorkerException(string code) : Exception("worker operation failed")
{
    public string Code { get; } = code;
}
