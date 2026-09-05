<?php
/**
 * Smart CSV column detection for lead imports. Users should never have to
 * rearrange their spreadsheet columns manually — we guess the mapping and
 * let them correct it before anything is written to the database.
 */

class CsvImporter
{
    const MAX_ROWS = 10000;

    private static array $synonyms = [
        'name' => ['name', 'fullname', 'leadname', 'customername', 'contactname', 'clientname', 'full name', 'lead name', 'customer name', 'contact name', 'client name'],
        'email' => ['email', 'emailaddress', 'contactemail', 'email address', 'e mail', 'mail'],
        'phone' => ['phone', 'phonenumber', 'mobile', 'mobilenumber', 'contactnumber', 'number', 'whatsapp', 'whatsappnumber', 'phone number', 'mobile number', 'contact number', 'cell'],
        'company' => ['company', 'companyname', 'organization', 'organisation', 'business', 'businessname', 'company name'],
        'source' => ['source', 'leadsource', 'channel', 'lead source'],
        'status' => ['status', 'leadstatus', 'currentstatus', 'stage', 'lead status', 'current status'],
        'notes' => ['notes', 'note', 'remark', 'remarks', 'comment', 'comments', 'message'],
    ];

    public static function normalizeHeader(string $h): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '', $h)));
    }

    /** @return array<string,int|null> field => column index */
    public static function detectMapping(array $headers): array
    {
        $normalized = array_map(function ($h) {
            return strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $h)));
        }, $headers);
        $tight = array_map([self::class, 'normalizeHeader'], $headers);

        $mapping = [];
        foreach (self::$synonyms as $field => $syns) {
            $found = null;
            foreach ($syns as $syn) {
                $synTight = self::normalizeHeader($syn);
                foreach ($tight as $i => $h) {
                    if ($h === $synTight) { $found = $i; break 2; }
                }
            }
            if ($found === null) {
                // fallback: partial contains match
                foreach ($syns as $syn) {
                    foreach ($normalized as $i => $h) {
                        if (strpos($h, $syn) !== false) { $found = $i; break 2; }
                    }
                }
            }
            $mapping[$field] = $found;
        }
        return $mapping;
    }

    /** Reads the CSV into [$headers, $rows] where $rows are plain indexed arrays. */
    public static function read(string $path): array
    {
        $rows = [];
        $headers = [];
        if (($fh = fopen($path, 'r')) === false) {
            throw new RuntimeException('Could not open uploaded file.');
        }
        // Strip a UTF-8 BOM if present
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }
        $i = 0;
        while (($data = fgetcsv($fh)) !== false) {
            if ($i === 0) {
                $headers = array_map('trim', $data);
            } else {
                if (count(array_filter($data, fn($v) => trim((string)$v) !== '')) === 0) { $i++; continue; }
                $rows[] = $data;
            }
            $i++;
            if ($i > self::MAX_ROWS) break;
        }
        fclose($fh);
        return [$headers, $rows];
    }

    public static function value(array $row, ?int $index): ?string
    {
        if ($index === null || !isset($row[$index])) return null;
        $v = trim((string)$row[$index]);
        return $v === '' ? null : $v;
    }
}
