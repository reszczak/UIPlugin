<?php
class PluginConfigfilessccglpiDocumentLocator
{
    public function findForComputer(Computer $computer): array
    {
        global $DB;

        $computerName = trim((string) ($computer->fields['name'] ?? ''));
        if ($computerName === '' || !$computer->getID()) {
            return [];
        }

        $uuid = trim((string) ($computer->fields['uuid'] ?? ''));

        $diTable  = Document_Item::getTable();
        $docTable = Document::getTable();

        $iterator = $DB->request([
            'SELECT'     => [
                "$docTable.id",
                "$docTable.name",
                "$docTable.filename",
                "$docTable.filepath",
                "$docTable.date_mod",
            ],
            'FROM'       => $diTable,
            'INNER JOIN' => [
                $docTable => [
                    'ON' => [$docTable => 'id', $diTable => 'documents_id'],
                ],
            ],
            'WHERE'      => [
                "$diTable.itemtype"   => Computer::class,
                "$diTable.items_id"   => $computer->getID(),
                "$docTable.is_deleted" => 0,
            ],
        ]);

        $quotedName = preg_quote($computerName, '/');
        $pattern = ($uuid !== '')
            ? '/^scc\.' . $quotedName . '@' . preg_quote($uuid, '/') . '\.html$/i'
            : '/^scc\.' . $quotedName . '@.+\.html$/i';

        $matches = [];
        foreach ($iterator as $row) {
            $filename = (string) ($row['filename'] ?? '');
            if ($filename !== '' && preg_match($pattern, $filename) === 1) {
                $matches[] = $row;
            }
        }

        usort($matches, static fn(array $a, array $b) => strcmp((string) $b['date_mod'], (string) $a['date_mod']));

        return $matches;
    }
}
