<?php

namespace Tests\MySql;

use App\Models\PriceBook;
use App\Services\PriceBookVersionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('mysql-integration')]
class PriceBookMySqlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) DB::connection()->getDatabaseName();
        $this->assertSame('moviemate_phase4_rehearsal', $database, "Unsafe MySQL integration database [{$database}].");
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]), Artisan::output());
    }

    public function test_parent_row_lock_serializes_publish_authority_on_mysql(): void
    {
        $book = PriceBook::query()->create([
            'code' => PriceBook::CHAIN_CODE,
            'name' => 'MovieMate Chain Price Book',
        ]);
        $draft = app(PriceBookVersionService::class)->createDraft($book, [
            'base_price_vnd' => 80_000,
            'effective_from' => '2026-01-01',
            'effective_until' => '2027-01-01',
        ]);

        DB::beginTransaction();
        try {
            DB::table('price_books')->where('id', $book->id)->lockForUpdate()->first();
            $second = $this->secondConnection();
            $second->exec('SET SESSION innodb_lock_wait_timeout = 1');
            $second->beginTransaction();
            try {
                $statement = $second->prepare('SELECT id FROM price_books WHERE id = ? FOR UPDATE');
                $statement->execute([$book->id]);
                $this->fail('A concurrent publisher must not pass the PriceBook serialization lock.');
            } catch (PDOException $exception) {
                $this->assertContains((string) $exception->getCode(), ['HY000', '40001']);
            } finally {
                if ($second->inTransaction()) {
                    $second->rollBack();
                }
            }
        } finally {
            DB::rollBack();
        }

        $published = app(PriceBookVersionService::class)->publish($draft);
        $this->assertSame('published', $published->status);
    }

    private function secondConnection(): PDO
    {
        return new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                config('database.connections.mysql.host'),
                config('database.connections.mysql.port'),
                config('database.connections.mysql.database'),
            ),
            (string) config('database.connections.mysql.username'),
            (string) config('database.connections.mysql.password'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
