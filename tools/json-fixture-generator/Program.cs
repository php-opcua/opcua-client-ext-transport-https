using System.Text;
using System.Text.Json;
using Opc.Ua;

namespace PhpOpcua.Tools.JsonFixtureGenerator;

// Emits reference fixtures produced by Opc.Ua.JsonEncoder / BinaryEncoder
// (UA-.NETStandard 1.5.378.134) in reversible mode, used by the PHP unit tests
// to validate that the PHP encoders/decoders are byte-exact with what a real
// UA-.NETStandard peer would emit.
//
// Two categories of fixtures:
//
// 1. Base-type fixtures (NodeId, Variant, DataValue, StatusCode, DateTime):
//    one `.json` file each, wrapped in `{"value": ...}` so the C# WriteX calls
//    can stay field-name-based.
//
// 2. Service-message fixtures (GetEndpointsRequest, GetEndpointsResponse, ...):
//    one `.json` (JsonEncoder.EncodeMessage output) plus one `.bin.b64` (the
//    BinaryEncoder.EncodeMessage bytes, base64-encoded). The PHP tests use the
//    pair to verify round-trip conversion in JsonHttpsEncoding.
internal static class Program
{
    private static readonly DateTime FixedTimestamp =
        new DateTime(2026, 5, 27, 10, 30, 45, 123, DateTimeKind.Utc).AddTicks(4560);

    public static int Main(string[] args)
    {
        var outputDir = args.Length > 0 ? args[0] : "/out";
        Directory.CreateDirectory(outputDir);

        var ctx = ServiceMessageContext.GlobalContext;

        WriteBaseTypeFixtures(ctx, outputDir);
        WriteServiceMessageFixtures(ctx, outputDir);

        Console.WriteLine($"Done. Output: {outputDir}");
        return 0;
    }

    private static void WriteBaseTypeFixtures(ServiceMessageContext ctx, string outputDir)
    {
        var fixtures = new Dictionary<string, Action<JsonEncoder>>
        {
            ["nodeid_numeric_ns0"] = e => e.WriteNodeId("value", new NodeId(2259u, 0)),
            ["nodeid_numeric_ns2"] = e => e.WriteNodeId("value", new NodeId(42u, 2)),
            ["nodeid_string_ns2"] = e => e.WriteNodeId("value", new NodeId("MyNode", 2)),
            ["nodeid_guid_ns2"] = e => e.WriteNodeId("value", new NodeId(new Guid("550e8400-e29b-41d4-a716-446655440000"), 2)),
            ["nodeid_opaque_ns2"] = e => e.WriteNodeId("value", new NodeId(new byte[] { 0xDE, 0xAD, 0xBE, 0xEF }, 2)),

            ["variant_scalar_int32"] = e => e.WriteVariant("value", new Variant(42)),
            ["variant_scalar_string"] = e => e.WriteVariant("value", new Variant("hello")),
            ["variant_scalar_bool_true"] = e => e.WriteVariant("value", new Variant(true)),
            ["variant_array_int32"] = e => e.WriteVariant("value", new Variant(new int[] { 1, 2, 3 })),
            ["variant_null"] = e => e.WriteVariant("value", Variant.Null),

            ["datavalue_full"] = e => e.WriteDataValue("value", new DataValue(new Variant(123))
            {
                StatusCode = StatusCodes.GoodCallAgain,
                SourceTimestamp = FixedTimestamp,
                SourcePicoseconds = 100,
                ServerTimestamp = FixedTimestamp.AddMilliseconds(50),
                ServerPicoseconds = 200,
            }),
            ["datavalue_value_only"] = e => e.WriteDataValue("value", new DataValue(new Variant(123))),
            ["datavalue_status_only"] = e => e.WriteDataValue("value", new DataValue(StatusCodes.BadInternalError)),

            ["statuscode_good"] = e => e.WriteStatusCode("value", StatusCodes.Good),
            ["statuscode_bad_invalid_argument"] = e => e.WriteStatusCode("value", StatusCodes.BadInvalidArgument),
            ["statuscode_bad_internal_error"] = e => e.WriteStatusCode("value", StatusCodes.BadInternalError),

            ["datetime_fixed"] = e => e.WriteDateTime("value", FixedTimestamp),
            ["datetime_min"] = e => e.WriteDateTime("value", DateTime.MinValue),
            ["datetime_epoch"] = e => e.WriteDateTime("value", new DateTime(1970, 1, 1, 0, 0, 0, DateTimeKind.Utc)),
        };

        foreach (var (name, action) in fixtures)
        {
            var json = EncodeJson(ctx, action);
            var pretty = PrettyPrint(json);
            File.WriteAllText(Path.Combine(outputDir, name + ".json"), pretty + "\n", new UTF8Encoding(false));
            Console.WriteLine($"  + {name}.json");
        }
    }

    private static void WriteServiceMessageFixtures(ServiceMessageContext ctx, string outputDir)
    {
        WriteServiceFixture(ctx, outputDir, "getendpoints_request_minimal", BuildGetEndpointsRequest());
        WriteServiceFixture(ctx, outputDir, "getendpoints_response_empty", BuildGetEndpointsResponseEmpty());
    }

    private static GetEndpointsRequest BuildGetEndpointsRequest()
    {
        return new GetEndpointsRequest
        {
            RequestHeader = new RequestHeader
            {
                AuthenticationToken = NodeId.Null,
                Timestamp = FixedTimestamp,
                RequestHandle = 1,
                ReturnDiagnostics = 0,
                AuditEntryId = null,
                TimeoutHint = 10000,
                AdditionalHeader = null,
            },
            EndpointUrl = "opc.https://localhost:4852/UA/TestServer",
            LocaleIds = null,
            ProfileUris = null,
        };
    }

    private static GetEndpointsResponse BuildGetEndpointsResponseEmpty()
    {
        return new GetEndpointsResponse
        {
            ResponseHeader = new ResponseHeader
            {
                Timestamp = FixedTimestamp,
                RequestHandle = 1,
                ServiceResult = StatusCodes.Good,
                ServiceDiagnostics = new DiagnosticInfo(),
                StringTable = new StringCollection(),
                AdditionalHeader = null,
            },
            Endpoints = new EndpointDescriptionCollection(),
        };
    }

    private static void WriteServiceFixture(
        ServiceMessageContext ctx,
        string outputDir,
        string baseName,
        IEncodeable message)
    {
        var binary = BinaryEncoder.EncodeMessage(message, ctx);
        var b64 = Convert.ToBase64String(binary);
        File.WriteAllText(Path.Combine(outputDir, baseName + ".bin.b64"), b64 + "\n", new UTF8Encoding(false));
        Console.WriteLine($"  + {baseName}.bin.b64 ({binary.Length} bytes)");

        using var encoder = new JsonEncoder(ctx, useReversibleEncoding: true);
        switch (message)
        {
            case IServiceRequest req:
                encoder.EncodeMessage(req);
                break;
            case IServiceResponse resp:
                encoder.EncodeMessage(resp);
                break;
            default:
                throw new InvalidOperationException($"Unsupported message type: {message.GetType().Name}");
        }
        var pretty = PrettyPrint(encoder.CloseAndReturnText());
        File.WriteAllText(Path.Combine(outputDir, baseName + ".json"), pretty + "\n", new UTF8Encoding(false));
        Console.WriteLine($"  + {baseName}.json");
    }

    private static string EncodeJson(ServiceMessageContext ctx, Action<JsonEncoder> writer)
    {
        using var encoder = new JsonEncoder(ctx, useReversibleEncoding: true);
        writer(encoder);
        return encoder.CloseAndReturnText();
    }

    private static string PrettyPrint(string json)
    {
        using var doc = JsonDocument.Parse(json);
        return JsonSerializer.Serialize(doc, new JsonSerializerOptions { WriteIndented = true });
    }
}
