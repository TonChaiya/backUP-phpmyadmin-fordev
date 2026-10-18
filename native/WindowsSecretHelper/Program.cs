using System.Text;
using System.Text.Json;
using BackupSecretHelper;

const int maximumInputBytes = 16 * 1024;
string operation = args.Length == 1 ? args[0] : string.Empty;

try
{
    byte[] buffer = new byte[maximumInputBytes + 1];
    using Stream input = Console.OpenStandardInput();
    int total = 0;
    while (total < buffer.Length)
    {
        int read = input.Read(buffer, total, buffer.Length - total);
        if (read == 0) break;
        total += read;
    }
    if (total == 0 || total > maximumInputBytes || input.ReadByte() != -1)
    {
        throw new WorkerProtocolException("worker_input_invalid");
    }
    string json = new UTF8Encoding(false, true).GetString(buffer, 0, total);
    WorkerRequest request = ProtocolParser.Parse(operation, json);
    WriteResponse(WorkerServices.Execute(request));
    return 0;
}
catch (WorkerProtocolException exception)
{
    WriteResponse(new Dictionary<string, object> { ["ok"] = false, ["code"] = exception.Code });
    return 9;
}
catch (SafeWorkerException exception)
{
    WriteResponse(new Dictionary<string, object> { ["ok"] = false, ["code"] = exception.Code });
    return 9;
}
catch
{
    WriteResponse(new Dictionary<string, object> { ["ok"] = false, ["code"] = "worker_failed" });
    return 9;
}

static void WriteResponse(Dictionary<string, object> response)
{
    Console.Out.Write(JsonSerializer.Serialize(response));
}
