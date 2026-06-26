<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\ContactImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Streams a stored CSV upload into a campaign's contacts. The file is read a
 * row at a time off the object store (constant memory, however large the list),
 * each row is validated and upserted by (campaign, email) so a re-run re-lands
 * the same state, and the outcome — imported/failed counts plus a per-row error
 * report — is recorded on the {@see ContactImport} as its status settles. A
 * malformed row is reported and skipped; only a file that cannot be read at all
 * fails the whole import.
 */
class ImportContacts implements ShouldQueue
{
    use Queueable;

    /**
     * The CSV column contract, carried by the file's header row and shared with
     * the export half (native fputcsv/fgetcsv, custom_fields a JSON cell).
     *
     * @var list<string>
     */
    protected array $columns = ['name', 'email', 'phone', 'custom_fields'];

    public function __construct(public ContactImport $import) {}

    /**
     * Drive the import, settling the record as Failed if the file itself cannot
     * be processed (a per-row problem is captured in the report, not fatal).
     */
    public function handle(): void
    {
        $this->import->update(['status' => ImportStatus::Processing]);

        try {
            $this->streamContacts();
        } catch (Throwable $e) {
            report($e);

            $this->import->update([
                'status' => ImportStatus::Failed,
                'errors' => [['row' => 0, 'reason' => 'The import file could not be processed: '.$e->getMessage()]],
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Stream the upload row by row, upsert the valid rows, and settle the record
     * as Completed with the tallied counts and error report.
     */
    protected function streamContacts(): void
    {
        $stream = Storage::disk($this->import->disk)->readStream($this->import->path);

        if (! is_resource($stream)) {
            throw new RuntimeException("The import file [{$this->import->path}] is missing on disk [{$this->import->disk}].");
        }

        $imported = 0;
        $failed = 0;
        $errors = [];

        /** @var array<string, int> $index header column name => position */
        $index = [];
        $header = null;
        $line = 0;

        try {
            $rows = LazyCollection::make(function () use ($stream): iterable {
                while (($row = fgetcsv($stream, escape: '')) !== false) {
                    yield $row;
                }
            });

            foreach ($rows as $row) {
                $line++;

                // The first row names the columns; import reads by header name.
                if ($header === null) {
                    $header = array_map(fn ($cell): string => trim((string) $cell), $row);
                    $index = array_flip($header);

                    continue;
                }

                if ($this->isBlankRow($row)) {
                    continue;
                }

                $email = Str::lower(trim((string) $this->cell($index, $row, 'email')));

                if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = ['row' => $line, 'reason' => 'The email is missing or not a valid address.'];
                    $failed++;

                    continue;
                }

                $rawCustomFields = $this->cell($index, $row, 'custom_fields');
                $customFields = null;

                if ($rawCustomFields !== null && trim($rawCustomFields) !== '') {
                    $customFields = json_decode($rawCustomFields, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $errors[] = ['row' => $line, 'reason' => 'The custom_fields value is not valid JSON.'];
                        $failed++;

                        continue;
                    }
                }

                $phone = $this->cell($index, $row, 'phone');

                $this->import->campaign->contacts()->updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => (string) $this->cell($index, $row, 'name'),
                        'phone' => ($phone === null || trim($phone) === '') ? null : $phone,
                        'custom_fields' => $customFields,
                    ],
                );

                $imported++;
            }
        } finally {
            fclose($stream);
        }

        $this->import->update([
            'imported_count' => $imported,
            'failed_count' => $failed,
            'errors' => $errors === [] ? null : $errors,
            'status' => ImportStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * Read a named column from a row by its header position, or null if the
     * column is absent from the header or missing on this row.
     *
     * @param  array<string, int>  $index
     * @param  array<int, string|null>  $row
     */
    protected function cell(array $index, array $row, string $column): ?string
    {
        if (! array_key_exists($column, $index)) {
            return null;
        }

        return $row[$index[$column]] ?? null;
    }

    /**
     * Whether every cell in the row is empty — a blank line to skip silently
     * rather than count as a failed row.
     *
     * @param  array<int, string|null>  $row
     */
    protected function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
