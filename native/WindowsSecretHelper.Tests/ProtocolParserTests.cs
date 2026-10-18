using BackupSecretHelper;
using Xunit;

namespace BackupSecretHelper.Tests;

public sealed class ProtocolParserTests
{
    [Fact]
    public void IdentityAcceptsOnlyEmptyObject()
    {
        var request = ProtocolParser.Parse("identity", "{}");
        Assert.Equal(WorkerOperation.Identity, request.Operation);
        Assert.Throws<WorkerProtocolException>(() => ProtocolParser.Parse("identity", "{\"unexpected\":true}"));
    }

    [Theory]
    [InlineData("protect", "{}")]
    [InlineData("protect", "{\"expected_sid\":\"S-1-5-18\",\"data_b64\":\"YQ==\",\"entropy_b64\":\"AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=\",\"extra\":true}")]
    [InlineData("identity", "{")]
    [InlineData("unknown", "{}")]
    public void RejectsMalformedOrNonExactRequests(string operation, string json)
    {
        var error = Assert.Throws<WorkerProtocolException>(() => ProtocolParser.Parse(operation, json));
        Assert.Equal("worker_input_invalid", error.Code);
        Assert.DoesNotContain(json, error.Message, StringComparison.Ordinal);
    }

    [Fact]
    public void ParsesBinaryRequestLosslessly()
    {
        byte[] data = [0, 1, 2, 127, 128, 255];
        byte[] entropy = Enumerable.Range(0, 32).Select(i => (byte)i).ToArray();
        var json = $$"""{"expected_sid":"S-1-5-21-1-2-3-1001","data_b64":"{{Convert.ToBase64String(data)}}","entropy_b64":"{{Convert.ToBase64String(entropy)}}"}""";
        var request = ProtocolParser.Parse("protect", json);
        Assert.Equal(data, request.Data);
        Assert.Equal(entropy, request.Entropy);
    }

    [Fact]
    public void RejectsOversizedDataAndWrongEntropy()
    {
        var oversized = Convert.ToBase64String(new byte[8193]);
        var shortEntropy = Convert.ToBase64String(new byte[31]);
        Assert.Throws<WorkerProtocolException>(() => ProtocolParser.Parse("protect", $$"""{"expected_sid":"S-1-5-18","data_b64":"{{oversized}}","entropy_b64":"{{Convert.ToBase64String(new byte[32])}}"}"""));
        Assert.Throws<WorkerProtocolException>(() => ProtocolParser.Parse("protect", $$"""{"expected_sid":"S-1-5-18","data_b64":"YQ==","entropy_b64":"{{shortEntropy}}"}"""));
    }
}
