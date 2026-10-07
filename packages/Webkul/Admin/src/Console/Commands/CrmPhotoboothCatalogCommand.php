<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Webkul\Admin\Services\PhotoboothCatalogService;

class CrmPhotoboothCatalogCommand extends Command
{
    protected $signature = 'crm:photobooth-catalog {--mapping=} {--apply} {--expect=} {--actor=} {--reason=}';

    protected $description = 'Preview or apply reviewed sequential product SKUs and Photobooth equipment templates.';

    public function handle(PhotoboothCatalogService $service): int
    {
        try {
            $mapping = [];
            if ($file = $this->option('mapping')) {
                if (! is_file($file) || ! is_readable($file)) {
                    throw new RuntimeException('File mapping tidak dapat dibaca.');
                }
                $mapping = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($mapping)) {
                    throw new RuntimeException('Mapping harus berupa JSON object.');
                }
            }
            $result = $this->option('apply')
                ? $service->apply($mapping, (string) $this->option('expect'), (int) $this->option('actor'), (string) $this->option('reason'))
                : $service->preview($mapping);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            if (! $this->option('apply')) {
                $this->info('PREVIEW: belum ada perubahan. Lengkapi mapping dan tinjau seluruh SKU/kategori sebelum apply.');
            }

            return empty($result['errors']) ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
