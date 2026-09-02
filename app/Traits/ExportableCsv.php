<?php

namespace App\Traits;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportableCsv
{
    public function exportCsv(array $headers, Collection $rows, string $filename): StreamedResponse
    {
        $callback = function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
