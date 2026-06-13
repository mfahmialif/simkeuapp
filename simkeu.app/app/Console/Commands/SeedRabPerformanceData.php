<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SeedRabPerformanceData extends Command
{
    private const MARKER = '[RAB-PERF]';

    private const MODULES = [
        'tatap_muka' => [
            'label' => 'Dosen Tatap Muka',
            'prefix' => 'TM',
            'rekap_table' => 'keuangan_pengeluaran_dosen_rekap',
            'temp_rekap_table' => 'tmp_rab_perf_rekap_tm',
        ],
        'kegiatan' => [
            'label' => 'Pegawai Kegiatan',
            'prefix' => 'KG',
            'rekap_table' => 'keuangan_pengeluaran_dosen_kegiatan_rekap',
            'temp_rekap_table' => 'tmp_rab_perf_rekap_kg',
        ],
        'dosen_bulanan' => [
            'label' => 'Dosen Bulanan',
            'prefix' => 'DB',
            'rekap_table' => 'keuangan_pengeluaran_dosen_bulanan_rekap',
            'temp_rekap_table' => 'tmp_rab_perf_rekap_db',
        ],
    ];

    protected $signature = 'app:seed-rab-performance
        {--rows=1000000 : Total data pengeluaran sintetis}
        {--rekaps=10000 : Total rekap sintetis}
        {--fresh : Hapus data sintetis lama sebelum membuat data baru}
        {--clean : Hanya hapus seluruh data sintetis RAB}
        {--force : Izinkan dijalankan di environment selain local/testing}';

    protected $description = 'Membuat data pengeluaran dan rekap berskala besar untuk pengujian performa modul RAB';

    public function handle(): int
    {
        if (! $this->option('force') && ! app()->environment(['local', 'testing'])) {
            $this->error('Command ini hanya boleh dijalankan di environment local/testing. Gunakan --force bila memang diperlukan.');

            return self::FAILURE;
        }

        DB::connection()->disableQueryLog();
        @set_time_limit(0);

        if ($this->option('clean')) {
            $this->cleanup();
            $this->info('Seluruh data sintetis RAB berhasil dibersihkan.');

            return self::SUCCESS;
        }

        $totalRows = filter_var($this->option('rows'), FILTER_VALIDATE_INT);
        $totalRekaps = filter_var($this->option('rekaps'), FILTER_VALIDATE_INT);

        if ($totalRows === false || $totalRows < count(self::MODULES)) {
            $this->error('Opsi --rows minimal 4.');

            return self::FAILURE;
        }

        if ($totalRekaps === false || $totalRekaps < count(self::MODULES)) {
            $this->error('Opsi --rekaps minimal 4.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->cleanup();
        } elseif ($this->syntheticDataExists()) {
            $this->error('Data sintetis RAB sudah ada. Jalankan kembali dengan --fresh atau gunakan --clean.');

            return self::FAILURE;
        }

        $detailCounts = $this->distribute($totalRows, count(self::MODULES));
        $rekapCounts = $this->distribute($totalRekaps, count(self::MODULES));
        $periodStart = CarbonImmutable::create(2021, 1, 1)->startOfMonth();
        $periodMonths = max(
            1,
            (int) $periodStart->diffInMonths(CarbonImmutable::now()->startOfMonth()) + 1
        );

        $this->newLine();
        $this->table(
            ['Modul', 'Detail', 'Rekap'],
            collect(self::MODULES)
                ->values()
                ->map(fn (array $module, int $index) => [
                    $module['label'],
                    number_format($detailCounts[$index]),
                    number_format($rekapCounts[$index]),
                ])
                ->all()
        );

        $startedAt = hrtime(true);

        try {
            $this->createRekaps($rekapCounts, $periodStart, $periodMonths);
            $this->createTemporaryMappings();

            $moduleKeys = array_keys(self::MODULES);
            $this->insertTatapMuka($detailCounts[0], $rekapCounts[0], $periodStart, $periodMonths);
            $this->line("  <info>✓</info> {$moduleKeys[0]}: ".number_format($detailCounts[0]).' detail');

            $this->insertKegiatan($detailCounts[1], $rekapCounts[1], $periodStart, $periodMonths);
            $this->line("  <info>✓</info> {$moduleKeys[1]}: ".number_format($detailCounts[1]).' detail');

            $this->insertBulanan(
                $detailCounts[2],
                $rekapCounts[2],
                $periodStart,
                $periodMonths,
                'dosen'
            );
            $this->line("  <info>✓</info> {$moduleKeys[2]}: ".number_format($detailCounts[2]).' detail');

            $this->insertBulanan(
                $detailCounts[3],
                $rekapCounts[3],
                $periodStart,
                $periodMonths,
                'staff'
            );
            $this->line("  <info>✓</info> {$moduleKeys[3]}: ".number_format($detailCounts[3]).' detail');
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            $this->warn('Data yang sempat masuk tetap bertanda [RAB-PERF]. Jalankan ulang dengan --fresh.');

            return self::FAILURE;
        } finally {
            $this->dropTemporaryMappings();
        }

        $elapsedSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;
        $counts = $this->syntheticCounts();

        $this->newLine();
        $this->info('Data performa RAB berhasil dibuat dalam '.number_format($elapsedSeconds, 2).' detik.');
        $this->table(
            ['Jenis', 'Jumlah'],
            [
                ['Total detail sintetis', number_format($counts['details'])],
                ['Total rekap sintetis', number_format($counts['rekaps'])],
            ]
        );

        return self::SUCCESS;
    }

    private function createRekaps(
        array $rekapCounts,
        CarbonImmutable $periodStart,
        int $periodMonths
    ): void {
        foreach (array_values(self::MODULES) as $index => $module) {
            $rows = [];

            for ($sequence = 0; $sequence < $rekapCounts[$index]; $sequence++) {
                $period = $periodStart->addMonths($sequence % $periodMonths);
                $rows[] = [
                    'nama' => sprintf(
                        '%s %s-%06d %s',
                        self::MARKER,
                        $module['prefix'],
                        $sequence + 1,
                        $period->format('M Y')
                    ),
                    'bulan_tahun' => $period->toDateString(),
                    'keterangan' => self::MARKER.' Rekap sintetis untuk uji performa modul RAB.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($rows) === 1000) {
                    DB::table($module['rekap_table'])->insert($rows);
                    $rows = [];
                }
            }

            if ($rows !== []) {
                DB::table($module['rekap_table'])->insert($rows);
            }
        }
    }

    private function createTemporaryMappings(): void
    {
        $this->createEmployeeMapping('dosen', 'tmp_rab_perf_pegawai_dosen');
        $this->createEmployeeMapping('staff', 'tmp_rab_perf_pegawai_staff');

        foreach (self::MODULES as $module) {
            $temporaryTable = $module['temp_rekap_table'];
            $rekapTable = $module['rekap_table'];

            DB::statement("DROP TEMPORARY TABLE IF EXISTS `{$temporaryTable}`");
            DB::statement(
                "CREATE TEMPORARY TABLE `{$temporaryTable}` ENGINE=InnoDB AS
                SELECT ROW_NUMBER() OVER (ORDER BY `id`) AS `seq`, `id`
                FROM `{$rekapTable}`
                WHERE `keterangan` LIKE ?",
                [self::MARKER.'%']
            );
            DB::statement("ALTER TABLE `{$temporaryTable}` ADD PRIMARY KEY (`seq`)");
        }
    }

    private function createEmployeeMapping(string $type, string $temporaryTable): void
    {
        $count = DB::table('pegawai')->where('tipe', $type)->count();

        if ($count === 0) {
            throw new RuntimeException("Tidak ada pegawai bertipe {$type} untuk data performa.");
        }

        DB::statement("DROP TEMPORARY TABLE IF EXISTS `{$temporaryTable}`");
        DB::statement(
            "CREATE TEMPORARY TABLE `{$temporaryTable}` ENGINE=InnoDB AS
            SELECT ROW_NUMBER() OVER (ORDER BY `id`) AS `seq`, `id`
            FROM `pegawai`
            WHERE `tipe` = ?",
            [$type]
        );
        DB::statement("ALTER TABLE `{$temporaryTable}` ADD PRIMARY KEY (`seq`)");
    }

    private function insertTatapMuka(
        int $count,
        int $rekapCount,
        CarbonImmutable $periodStart,
        int $periodMonths
    ): void {
        $employeeCount = (int) DB::table('tmp_rab_perf_pegawai_dosen')->count();
        $numbers = $this->numberSource($count);
        $startDate = $periodStart->toDateString();

        DB::statement(
            "INSERT INTO `keuangan_pengeluaran_dosen` (
                `pegawai_id`, `rekap_id`, `tanggal`, `jam`, `jam_mengajar_double_degree`,
                `hari`, `hari_transport_motor`, `hari_transport_mobil`,
                `hari_transport_mobil_tol`, `hari_transport_mobil_tanpa_tol`,
                `transport`, `transport_motor`, `transport_mobil`,
                `transport_mobil_tol`, `transport_mobil_tanpa_tol`,
                `barokah`, `barokah_mengajar_biasa`, `barokah_mengajar_double_degree`,
                `barokah_uas`, `jumlah_mahasiswa_uas`, `barokah_sempro`, `jam_sempro`,
                `keterangan_sempro`, `total`, `jenis_pembayaran`, `keterangan`,
                `created_at`, `updated_at`
            )
            SELECT
                pegawai.`id`,
                rekap.`id`,
                DATE_ADD(
                    DATE_ADD('{$startDate}', INTERVAL MOD(rekap.`seq` - 1, {$periodMonths}) MONTH),
                    INTERVAL MOD(FLOOR(numbers.`n` / {$rekapCount}), 28) DAY
                ),
                1 + MOD(numbers.`n`, 6),
                MOD(numbers.`n`, 3),
                1 + MOD(numbers.`n`, 5),
                1 + MOD(numbers.`n`, 5),
                MOD(numbers.`n`, 3),
                MOD(numbers.`n`, 2),
                MOD(numbers.`n` + 1, 2),
                50000 + (MOD(numbers.`n`, 6) * 10000),
                25000 + (MOD(numbers.`n`, 5) * 5000),
                25000 + (MOD(numbers.`n`, 4) * 10000),
                MOD(numbers.`n`, 2) * 25000,
                MOD(numbers.`n` + 1, 2) * 20000,
                100000 + (MOD(numbers.`n`, 8) * 25000),
                75000 + (MOD(numbers.`n`, 8) * 20000),
                MOD(numbers.`n`, 3) * 50000,
                MOD(numbers.`n`, 4) * 30000,
                MOD(numbers.`n`, 45),
                MOD(numbers.`n`, 3) * 50000,
                MOD(numbers.`n`, 3),
                IF(MOD(numbers.`n`, 3) = 0, 'Seminar proposal sintetis', NULL),
                200000 + (MOD(numbers.`n`, 20) * 25000),
                IF(MOD(numbers.`n`, 4) = 0, 'Transfer', 'CUS BSI'),
                CONCAT(?, ' Tatap muka #', LPAD(numbers.`n` + 1, 7, '0')),
                NOW(),
                NOW()
            FROM {$numbers}
            INNER JOIN `tmp_rab_perf_pegawai_dosen` AS pegawai
                ON pegawai.`seq` = MOD(numbers.`n`, {$employeeCount}) + 1
            INNER JOIN `tmp_rab_perf_rekap_tm` AS rekap
                ON rekap.`seq` = MOD(numbers.`n`, {$rekapCount}) + 1",
            [self::MARKER]
        );
    }

    private function insertKegiatan(
        int $count,
        int $rekapCount,
        CarbonImmutable $periodStart,
        int $periodMonths
    ): void {
        $employeeCount = (int) DB::table('tmp_rab_perf_pegawai_dosen')->count();
        $numbers = $this->numberSource($count);
        $startDate = $periodStart->toDateString();

        DB::statement(
            "INSERT INTO `keuangan_pengeluaran_dosen_kegiatan` (
                `pegawai_id`, `rekap_id`, `tanggal`, `nama_kegiatan`, `transport`,
                `barokah`, `total`, `jenis_pembayaran`, `keterangan`,
                `created_at`, `updated_at`
            )
            SELECT
                pegawai.`id`,
                rekap.`id`,
                DATE_ADD(
                    DATE_ADD('{$startDate}', INTERVAL MOD(rekap.`seq` - 1, {$periodMonths}) MONTH),
                    INTERVAL MOD(FLOOR(numbers.`n` / {$rekapCount}), 28) DAY
                ),
                CONCAT('Kegiatan sintetis ', LPAD(MOD(numbers.`n`, 500) + 1, 3, '0')),
                50000 + (MOD(numbers.`n`, 8) * 25000),
                100000 + (MOD(numbers.`n`, 12) * 50000),
                150000 + (MOD(numbers.`n`, 20) * 50000),
                IF(MOD(numbers.`n`, 4) = 0, 'Transfer', 'CUS BSI'),
                CONCAT(?, ' Kegiatan #', LPAD(numbers.`n` + 1, 7, '0')),
                NOW(),
                NOW()
            FROM {$numbers}
            INNER JOIN `tmp_rab_perf_pegawai_dosen` AS pegawai
                ON pegawai.`seq` = MOD(numbers.`n`, {$employeeCount}) + 1
            INNER JOIN `tmp_rab_perf_rekap_kg` AS rekap
                ON rekap.`seq` = MOD(numbers.`n`, {$rekapCount}) + 1",
            [self::MARKER]
        );
    }

    private function insertBulanan(
        int $count,
        int $rekapCount,
        CarbonImmutable $periodStart,
        int $periodMonths,
        string $employeeType
    ): void {
        $isDosen = $employeeType === 'dosen';
        $employeeTable = $isDosen
            ? 'tmp_rab_perf_pegawai_dosen'
            : 'tmp_rab_perf_pegawai_staff';
        $rekapTable = $isDosen
            ? 'tmp_rab_perf_rekap_db'
            : 'tmp_rab_perf_rekap_sb';
        $employeeCount = (int) DB::table($employeeTable)->count();
        $numbers = $this->numberSource($count);
        $startDate = $periodStart->toDateString();
        $periodExpression = "DATE_ADD('{$startDate}', INTERVAL MOD(rekap.`seq` - 1, {$periodMonths}) MONTH)";

        if ($isDosen) {
            $componentColumns = '
                0,
                0,
                0,
                2000000 + (MOD(numbers.`n`, 8) * 250000),
                MOD(numbers.`n`, 4) * 500000,
                2000000 + (MOD(numbers.`n`, 8) * 250000) + (MOD(numbers.`n`, 4) * 500000)';
            $label = 'Dosen bulanan';
        } else {
            $componentColumns = '
                20 + MOD(numbers.`n`, 7),
                50000 + (MOD(numbers.`n`, 4) * 10000),
                500000 + (MOD(numbers.`n`, 6) * 100000),
                0,
                0,
                ((20 + MOD(numbers.`n`, 7)) * (50000 + (MOD(numbers.`n`, 4) * 10000)))
                    + 500000 + (MOD(numbers.`n`, 6) * 100000)';
            throw new RuntimeException('Tipe pegawai bulanan tidak didukung.');
        }

        DB::statement(
            "INSERT INTO `keuangan_pengeluaran_pegawai_bulanan` (
                `pegawai_id`, `pegawai_tipe`, `rekap_id`, `bulan`, `tahun`, `tanggal`, `hari`,
                `barokah_harian`, `barokah_bulanan`, `barokah_dosen_tetap`,
                `barokah_struktural`, `total`, `jenis_pembayaran`, `keterangan`,
                `created_at`, `updated_at`
            )
            SELECT
                pegawai.`id`,
                '{$employeeType}',
                rekap.`id`,
                MONTH({$periodExpression}),
                YEAR({$periodExpression}),
                DATE_ADD(
                    {$periodExpression},
                    INTERVAL MOD(FLOOR(numbers.`n` / {$rekapCount}), 28) DAY
                ),
                {$componentColumns},
                IF(MOD(numbers.`n`, 4) = 0, 'Transfer', 'CUS BSI'),
                CONCAT(?, ' {$label} #', LPAD(numbers.`n` + 1, 7, '0')),
                NOW(),
                NOW()
            FROM {$numbers}
            INNER JOIN `{$employeeTable}` AS pegawai
                ON pegawai.`seq` = MOD(numbers.`n`, {$employeeCount}) + 1
            INNER JOIN `{$rekapTable}` AS rekap
                ON rekap.`seq` = MOD(numbers.`n`, {$rekapCount}) + 1",
            [self::MARKER]
        );
    }

    private function numberSource(int $count): string
    {
        $digitsNeeded = max(1, strlen((string) ($count - 1)));
        $digitTable = '(SELECT 0 AS `n` UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL
            SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL
            SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9)';
        $tables = [];
        $terms = [];

        for ($digit = 0; $digit < $digitsNeeded; $digit++) {
            $alias = "digit{$digit}";
            $tables[] = "{$digitTable} AS `{$alias}`";
            $terms[] = $digit === 0
                ? "`{$alias}`.`n`"
                : "`{$alias}`.`n` * ".(10 ** $digit);
        }

        return sprintf(
            '(SELECT %s AS `n` FROM %s LIMIT %d) AS `numbers`',
            implode(' + ', $terms),
            implode(' CROSS JOIN ', $tables),
            $count
        );
    }

    private function cleanup(): void
    {
        $this->line('Membersihkan data sintetis RAB lama...');

        DB::table('keuangan_pengeluaran_dosen')
            ->where('keterangan', 'like', self::MARKER.'%')
            ->delete();
        DB::table('keuangan_pengeluaran_dosen_kegiatan')
            ->where('keterangan', 'like', self::MARKER.'%')
            ->delete();
        DB::table('keuangan_pengeluaran_pegawai_bulanan')
            ->where('keterangan', 'like', self::MARKER.'%')
            ->delete();

        foreach (self::MODULES as $module) {
            DB::table($module['rekap_table'])
                ->where('keterangan', 'like', self::MARKER.'%')
                ->delete();
        }
    }

    private function syntheticDataExists(): bool
    {
        if (
            DB::table('keuangan_pengeluaran_dosen')
                ->where('keterangan', 'like', self::MARKER.'%')
                ->exists()
            || DB::table('keuangan_pengeluaran_dosen_kegiatan')
                ->where('keterangan', 'like', self::MARKER.'%')
                ->exists()
            || DB::table('keuangan_pengeluaran_pegawai_bulanan')
                ->where('keterangan', 'like', self::MARKER.'%')
                ->exists()
        ) {
            return true;
        }

        foreach (self::MODULES as $module) {
            if (
                DB::table($module['rekap_table'])
                    ->where('keterangan', 'like', self::MARKER.'%')
                    ->exists()
            ) {
                return true;
            }
        }

        return false;
    }

    private function syntheticCounts(): array
    {
        $details = DB::table('keuangan_pengeluaran_dosen')
            ->where('keterangan', 'like', self::MARKER.'%')
            ->count();
        $details += DB::table('keuangan_pengeluaran_dosen_kegiatan')
            ->where('keterangan', 'like', self::MARKER.'%')
            ->count();
        $details += DB::table('keuangan_pengeluaran_pegawai_bulanan')
            ->where('keterangan', 'like', self::MARKER.'%')
            ->count();

        $rekaps = 0;
        foreach (self::MODULES as $module) {
            $rekaps += DB::table($module['rekap_table'])
                ->where('keterangan', 'like', self::MARKER.'%')
                ->count();
        }

        return compact('details', 'rekaps');
    }

    private function dropTemporaryMappings(): void
    {
        $tables = [
            'tmp_rab_perf_pegawai_dosen',
            'tmp_rab_perf_pegawai_staff',
            ...array_column(self::MODULES, 'temp_rekap_table'),
        ];

        foreach ($tables as $table) {
            DB::statement("DROP TEMPORARY TABLE IF EXISTS `{$table}`");
        }
    }

    private function distribute(int $total, int $buckets): array
    {
        $base = intdiv($total, $buckets);
        $remainder = $total % $buckets;
        $result = array_fill(0, $buckets, $base);

        for ($index = 0; $index < $remainder; $index++) {
            $result[$index]++;
        }

        return $result;
    }
}
