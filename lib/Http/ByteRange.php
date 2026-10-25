<?php

namespace OCA\SharingPath\Http;

/** Parse bounded byte ranges: null means ignore, [] means unsatisfiable. */
class ByteRange
{
    public static function parse(string $header, int $size): ?array
    {
        if (strpos($header, 'bytes=') !== 0 || $size < 0) {
            return null;
        }
        $parts = explode(',', substr($header, 6));
        // Do not amplify one request into an unbounded number of reads.
        if (count($parts) > 16) {
            return null;
        }
        $ranges = [];
        foreach ($parts as $part) {
            if (!preg_match('/^\s*(\d*)-(\d*)\s*$/D', $part, $match) || ($match[1] === '' && $match[2] === '')) {
                return null;
            }
            if ($match[1] === '') {
                $length = min((int) $match[2], $size);
                if ($length > 0) {
                    $ranges[] = ['from' => $size - $length, 'to' => $size - 1];
                }
            } else {
                $start = (int) $match[1];
                $end = $match[2] === '' ? $size - 1 : min((int) $match[2], $size - 1);
                if ($start <= $end && $start < $size) {
                    $ranges[] = ['from' => $start, 'to' => $end];
                }
            }
        }
        return $ranges;
    }
}
