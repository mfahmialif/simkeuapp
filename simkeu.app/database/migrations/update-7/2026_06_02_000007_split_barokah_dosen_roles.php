<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_ROLE = 'barokahdosen';
    private const TATAPMUKA_ROLE = 'barokahdosen_tatapmuka';
    private const KEGIATAN_ROLE = 'barokahdosen_kegiatan';
    private const BULANAN_ROLE = 'barokahdosen_bulanan';

    public function up(): void
    {
        $oldRole = DB::table('role')->where('name', self::OLD_ROLE)->first();
        $tatapmukaRole = DB::table('role')->where('name', self::TATAPMUKA_ROLE)->first();

        if ($oldRole && $tatapmukaRole) {
            DB::table('users')
                ->where('role_id', $oldRole->id)
                ->update(['role_id' => $tatapmukaRole->id]);

            DB::table('role')->where('id', $oldRole->id)->delete();
        } elseif ($oldRole) {
            DB::table('role')
                ->where('id', $oldRole->id)
                ->update([
                    'name' => self::TATAPMUKA_ROLE,
                    'keterangan' => 'Barokah Dosen Tatapmuka',
                    'updated_at' => now(),
                ]);
        } else {
            $this->ensureRole(self::TATAPMUKA_ROLE, 'Barokah Dosen Tatapmuka');
        }

        $this->ensureRole(self::KEGIATAN_ROLE, 'Barokah Dosen Kegiatan');
        $this->ensureRole(self::BULANAN_ROLE, 'Barokah Dosen Bulanan');
    }

    public function down(): void
    {
        $oldRole = DB::table('role')->where('name', self::OLD_ROLE)->first();
        $tatapmukaRole = DB::table('role')->where('name', self::TATAPMUKA_ROLE)->first();

        if ($tatapmukaRole && $oldRole) {
            DB::table('users')
                ->where('role_id', $tatapmukaRole->id)
                ->update(['role_id' => $oldRole->id]);

            DB::table('role')->where('id', $tatapmukaRole->id)->delete();
        } elseif ($tatapmukaRole) {
            DB::table('role')
                ->where('id', $tatapmukaRole->id)
                ->update([
                    'name' => self::OLD_ROLE,
                    'keterangan' => 'Barokah Dosen',
                    'updated_at' => now(),
                ]);
        } else {
            $this->ensureRole(self::OLD_ROLE, 'Barokah Dosen');
        }

        $fallbackRole = DB::table('role')->where('name', self::OLD_ROLE)->first();
        foreach ([self::KEGIATAN_ROLE, self::BULANAN_ROLE] as $roleName) {
            $role = DB::table('role')->where('name', $roleName)->first();
            if (! $role) {
                continue;
            }

            if ($fallbackRole) {
                DB::table('users')
                    ->where('role_id', $role->id)
                    ->update(['role_id' => $fallbackRole->id]);
            }

            DB::table('role')->where('id', $role->id)->delete();
        }
    }

    private function ensureRole(string $name, string $keterangan): void
    {
        $role = DB::table('role')->where('name', $name)->first();

        if ($role) {
            DB::table('role')
                ->where('id', $role->id)
                ->update([
                    'keterangan' => $keterangan,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('role')->insert([
            'name' => $name,
            'keterangan' => $keterangan,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
