<?php

namespace Firebird\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

/**
 * Megabit: the Schema Builder operations used by migrations, against a real
 * Firebird (3, 4 or 5).
 */
class MigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('mb_migrations');
        Schema::dropIfExists('MB_RUC');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('mb_migrations');
        Schema::dropIfExists('MB_RUC');

        parent::tearDown();
    }

    #[Test]
    public function increments_generates_ids_like_laravels_migrations_table(): void
    {
        Schema::create('mb_migrations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
        });

        DB::table('mb_migrations')->insert(['migration' => 'first', 'batch' => 1]);
        $id = DB::table('mb_migrations')->insertGetId(['migration' => 'second', 'batch' => 1]);

        $this->assertSame([1, 2], DB::table('mb_migrations')->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(2, (int) $id);
    }

    #[Test]
    public function named_primary_key_indexes_and_column_changes(): void
    {
        Schema::create('MB_RUC', function (Blueprint $table) {
            $table->string('RUC', 20);
            $table->integer('DV');
            $table->string('NOME', 200)->nullable();
            $table->primary(['RUC', 'DV'], 'MB_RUC_PK');
            $table->index('NOME', 'MB_RUC_IDX_NOME');
        });

        $constraint = DB::selectOne("SELECT TRIM(RDB\$CONSTRAINT_NAME) AS N FROM RDB\$RELATION_CONSTRAINTS WHERE RDB\$RELATION_NAME = 'MB_RUC' AND RDB\$CONSTRAINT_TYPE = 'PRIMARY KEY'");
        $this->assertSame('MB_RUC_PK', $constraint->N);
        // getIndexes() returns lower-case names, like the other drivers; Laravel
        // 13's hasIndex() lower-cases the argument, Laravel 12's does not.
        $this->assertTrue(Schema::hasIndex('MB_RUC', 'mb_ruc_idx_nome'));
        $this->assertTrue(Schema::hasIndex('MB_RUC', ['RUC', 'DV'], 'primary'));
        $this->assertFalse(Schema::hasIndex('MB_RUC', ['NOME'], 'unique'));

        Schema::table('MB_RUC', fn (Blueprint $table) => $table->string('PORTE', 10)->nullable());
        $this->assertTrue(Schema::hasColumn('MB_RUC', 'PORTE'));

        DB::table('MB_RUC')->insert(['RUC' => '80000001', 'DV' => 1, 'NOME' => 'Ñandutí', 'PORTE' => 'MEDIANO']);

        Schema::table('MB_RUC', fn (Blueprint $table) => $table->dropColumn('PORTE'));
        Schema::table('MB_RUC', fn (Blueprint $table) => $table->dropIndex('MB_RUC_IDX_NOME'));

        $this->assertFalse(Schema::hasColumn('MB_RUC', 'PORTE'));
        $this->assertFalse(Schema::hasIndex('MB_RUC', 'mb_ruc_idx_nome'));
        $this->assertSame('Ñandutí', DB::table('MB_RUC')->value('NOME'));
    }
}
