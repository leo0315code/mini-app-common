<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsCsv
{
    /**
     * 将二维数组以 GBK 友好的 CSV 流式输出下载。
     *
     * @param  list<string>       $headers  CSV 表头
     * @param  iterable<array>    $rows     数据行（需和表头一一对应）
     */
    protected static function streamCsvDownload(array $headers, iterable $rows, string $filename): StreamedResponse
    {
        return Response::stream(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'wb');

            // UTF-8 BOM，方便 Excel/WPS 直接识别中文
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    /**
     * 构造一个通用「批量导出所选（CSV）」Bulk Action。
     *
     * @param  array<string, string>  $columnMap  列键 => 展示表头（顺序决定输出顺序）
     * @param  \Closure(object): array<string, mixed>|null  $rowCallback  每一行自定义格式化
     */
    protected static function buildExportSelectedBulkAction(
        array $columnMap,
        string $label = '导出所选',
        string $icon = 'heroicon-o-arrow-down-tray',
        string $fileNamePrefix = 'export',
        ?\Closure $rowCallback = null,
    ) {
        return \Filament\Actions\BulkAction::make('exportSelectedCsv')
            ->label($label)
            ->icon($icon)
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) use ($columnMap, $fileNamePrefix, $rowCallback): StreamedResponse {
                $headers = array_values($columnMap);
                $keys = array_keys($columnMap);

                $rows = $records->map(function ($record) use ($keys, $rowCallback) {
                    if ($rowCallback !== null) {
                        return $rowCallback($record);
                    }

                    return collect($keys)
                        ->mapWithKeys(fn ($key) => [$key => data_get($record, $key, '')])
                        ->all();
                });

                $filename = sprintf('%s_%s.csv', $fileNamePrefix, now()->format('Ymd_His'));

                return self::streamCsvDownload($headers, $rows, $filename);
            });
    }

    /**
     * 构造「按当前筛选条件导出全部（CSV）」Header Action。
     *
     * @param  array<string, string>  $columnMap
     * @param  \Closure(Builder): Builder  $queryCallback  在应用筛选基础上再追加/修改查询
     */
    protected static function buildExportAllHeaderAction(
        Builder $baseQuery,
        array $columnMap,
        string $label = '导出全部',
        string $icon = 'heroicon-o-arrow-down-tray',
        string $fileNamePrefix = 'export',
        ?\Closure $queryCallback = null,
        ?\Closure $rowCallback = null,
    ) {
        return \Filament\Actions\Action::make('exportAllCsv')
            ->label($label)
            ->icon($icon)
            ->color('gray')
            ->action(function () use ($baseQuery, $columnMap, $fileNamePrefix, $queryCallback, $rowCallback): StreamedResponse {
                $query = clone $baseQuery;
                if ($queryCallback !== null) {
                    $query = $queryCallback($query) ?? $query;
                }

                $headers = array_values($columnMap);
                $keys = array_keys($columnMap);

                $cursor = $query->cursor();

                $rows = (function () use ($cursor, $keys, $rowCallback): iterable {
                    foreach ($cursor as $record) {
                        if ($rowCallback !== null) {
                            yield $rowCallback($record);

                            continue;
                        }

                        yield collect($keys)
                            ->mapWithKeys(fn ($key) => [$key => data_get($record, $key, '')])
                            ->all();
                    }
                })();

                $filename = sprintf('%s_%s.csv', $fileNamePrefix, now()->format('Ymd_His'));

                return self::streamCsvDownload($headers, $rows, $filename);
            });
    }
}
