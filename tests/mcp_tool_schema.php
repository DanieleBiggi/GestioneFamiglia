<?php
declare(strict_types=1);
// Regression test for PHP empty associative objects in MCP tool input schemas.
$source = file_get_contents(__DIR__ . '/../api/mcp.php');
if ($source === false || !str_contains($source, "'properties' => (object)\$properties")) {
    fwrite(STDERR, "MCP inputSchema properties must encode as JSON object.\n");
    exit(1);
}
$schema = ['type' => 'object', 'properties' => (object)[], 'required' => [], 'additionalProperties' => false];
$data = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
if (!is_object($data->properties)) {
    fwrite(STDERR, "Empty properties serialized incorrectly.\n");
    exit(1);
}
echo "MCP empty schema JSON object OK\n";
