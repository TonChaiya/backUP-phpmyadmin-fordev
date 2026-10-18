using BackupSecretHelper;
using Xunit;

namespace BackupSecretHelper.Tests;

public sealed class AclPathPolicyTests
{
    [Fact]
    public void AllowsOnlyFixedDirectoryAndExactCredentialNames()
    {
        Assert.True(AclPathPolicy.IsApprovedDirectory(AclPathPolicy.SecretDirectory));
        Assert.True(AclPathPolicy.IsApprovedFile(Path.Combine(AclPathPolicy.SecretDirectory, "backup-reader.dpapi.json")));
        Assert.True(AclPathPolicy.IsApprovedFile(Path.Combine(AclPathPolicy.SecretDirectory, ".restore-runner.dpapi.json.0123456789abcdef01234567.tmp")));
    }

    [Theory]
    [InlineData("C:\\ProgramData\\BackupPhpMyAdmin\\SecretProvider\\evil.json")]
    [InlineData("C:\\ProgramData\\BackupPhpMyAdmin\\SecretProvider\\secrets\\other.json")]
    [InlineData("C:\\ProgramData\\BackupPhpMyAdmin\\SecretProvider\\secrets\\..\\evil.json")]
    [InlineData("C:\\laragon\\www\\backUP-phpmyadmin-fordev\\storage\\private\\restore-integration-secrets\\backup-reader.dpapi.json")]
    public void RejectsAnyOtherPath(string path)
    {
        Assert.False(AclPathPolicy.IsApprovedFile(path));
    }
}
