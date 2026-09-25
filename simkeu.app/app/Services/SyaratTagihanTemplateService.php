<?php

namespace App\Services;

use App\Models\KeuanganSyaratTagihan;
use App\Models\KeuanganTagihan;

class SyaratTagihanTemplateService
{
    /**
     * Parse and categorize distinct tagihan names from the database.
     */
    public function getParsedTagihan(): array
    {
        $names = KeuanganTagihan::query()
            ->select('nama')
            ->distinct()
            ->whereNotNull('nama')
            ->where('nama', '!=', '')
            ->whereNull('nim')
            ->pluck('nama')
            ->values();

        $parsed = [];
        foreach ($names as $name) {
            $n = trim((string) $name);
            $upper = strtoupper($n);

            // Extract semester number if present
            $smt = null;
            if (preg_match('/(?:SEMESTER|SMT)\s*0*(\d+)\b/i', $n, $m)) {
                $smt = (int) $m[1];
            } elseif (preg_match('/^SEMESTER\s*0*(\d+)$/i', $n, $m)) {
                $smt = (int) $m[1];
            }

            // Extract month number if present (e.g., SPP BULAN 1)
            $bulan = null;
            if (preg_match('/(?:BULAN|BLN)\s*0*(\d+)\b/i', $n, $m)) {
                $bulan = (int) $m[1];
            }

            $type = 'other';
            if (stripos($upper, 'REGISTRASI') !== false || stripos($upper, 'DAFTAR ULANG') !== false || stripos($upper, 'HEREGISTRASI') !== false || stripos($upper, 'HERREGISTRASI') !== false) {
                $type = 'registrasi';
            } elseif (stripos($upper, 'UTS') !== false) {
                $type = 'uts';
            } elseif (stripos($upper, 'UAS') !== false && stripos($upper, 'SUSULAN') === false) {
                $type = 'uas';
            } elseif (stripos($upper, 'SPP') !== false || preg_match('/^SEMESTER\s*\d+$/i', $n)) {
                $type = 'spp';
            } elseif (stripos($upper, 'SUMBANGAN PENDIDIKAN') !== false) {
                $type = 'sumbangan_pendidikan';
            } elseif (stripos($upper, 'SUMBANGAN PERPUS') !== false || stripos($upper, 'PERPUSTAKAAN') !== false) {
                $type = 'sumbangan_perpus';
            } elseif (stripos($upper, 'SKRIPSI') !== false) {
                $type = 'skripsi';
            } elseif (stripos($upper, 'WISUDA') !== false) {
                $type = 'wisuda';
            }

            $parsed[] = [
                'nama' => $n,
                'smt' => $smt,
                'bulan' => $bulan,
                'type' => $type,
            ];
        }

        return $parsed;
    }

    /**
     * Generate template rules array based on parsed tagihan and selected categories.
     */
    public function generateTemplateRules(array $options = []): array
    {
        $optAlurSiklus = $options['alur_siklus'] ?? true;
        $optAntarSemester = $options['antar_semester'] ?? true;
        $optSppBulanan = $options['spp_bulanan'] ?? true;
        $optAkhirWisuda = $options['akhir_wisuda'] ?? true;

        // Template tetap exclude semua tagihan perorangan apapun kondisinya
        $names = KeuanganTagihan::query()
            ->select('nama')
            ->distinct()
            ->whereNotNull('nama')
            ->where('nama', '!=', '')
            ->whereNull('nim')
            ->pluck('nama')
            ->toArray();

        $rules = [];

        $addRule = function ($target, $syarat, $ket, $kategori) use (&$rules) {
            $target = trim((string) $target);
            $syarat = ($syarat !== null && trim((string) $syarat) !== '') ? trim((string) $syarat) : null;

            if ($target === '') {
                return;
            }

            if ($syarat !== null && strcasecmp($target, $syarat) === 0) {
                return;
            }

            $key = strtolower($target).'___'.($syarat !== null ? strtolower($syarat) : '__NULL__');
            if (! isset($rules[$key])) {
                $rules[$key] = [
                    'tagihan_nama' => $target,
                    'syarat_nama' => $syarat,
                    'keterangan' => $ket,
                    'kategori' => $kategori,
                    'is_active' => true,
                ];
            }
        };

        // Helper untuk mencocokkan nama tagihan
        $findName = function ($pattern) use ($names) {
            foreach ($names as $n) {
                if (preg_match($pattern, $n)) {
                    return $n;
                }
            }

            return null;
        };

        foreach ($names as $n) {
            // A. Semester Pendek (dapat dibayar langsung tanpa prasyarat)
            if (str_contains($n, 'SEMESTER PENDEK')) {
                $addRule($n, null, 'Semester Pendek dapat dibayar langsung tanpa prasyarat', 'Semester Pendek');
                continue;
            }

            $upper = strtoupper(trim($n));

            // B. UTS (Semester 1..8 butuh SPP semester terkait; Semester > 8 butuh Herregistrasi/Registrasi semester terkait)
            if (preg_match('/^UTS\s+SEMESTER\s+(\d+)$/i', $n, $m)) {
                if ($optAlurSiklus) {
                    $smt = (int) $m[1];
                    if ($smt <= 8) {
                        $spp = $findName("/^(?:SPP\s+)?SEMESTER\s+$smt$/i");
                        if ($spp) {
                            $addRule($n, $spp, "UTS Semester {$smt} wajib melunasi SPP Semester {$smt}", 'Alur Dalam Semester');
                        }
                    } else {
                        $reg = $findName("/^(?:REGISTRASI|REGISTASI|HER+EGISTRASI|DAFTAR\s+ULANG)\s+SEMESTER\s+$smt$/i");
                        if ($reg) {
                            $addRule($n, $reg, "UTS Semester {$smt} wajib melunasi Registrasi / Herregistrasi Semester {$smt}", 'Alur Dalam Semester');
                        }
                    }
                }
                continue;
            }

            // C. Ujian Khusus Semester 5
            if (preg_match('/^UJIAN\s+SEMESTER\s+(\d+)$/i', $n, $m)) {
                if ($optAlurSiklus) {
                    $smt = (int) $m[1];
                    $spp = $findName("/^(?:SPP\s+)?SEMESTER\s+$smt$/i");
                    if ($spp) {
                        $addRule($n, $spp, "Ujian Semester {$smt} wajib melunasi SPP Semester {$smt}", 'Alur Dalam Semester');
                    }
                }
                continue;
            }

            // D. UAS (Mencakup variasi seperti UAS SEMESTER X, UAS SEMESTE 8, UAS SEMESTER SEMESTER X, UAS SEMESTER 10)
            if (preg_match('/^UAS\s+(?:SEMESTER\s+)?(?:SEMESTER\s+)?(?:SEMESTE\s+)?(\d+)$/i', $n, $m)) {
                if ($optAlurSiklus) {
                    $smt = (int) $m[1];
                    $uts = $findName("/^UTS\s+SEMESTER\s+$smt$/i") ?: $findName("/^(?:SPP\s+)?SEMESTER\s+$smt$/i");
                    if ($uts) {
                        $addRule($n, $uts, "UAS Semester {$smt} wajib melunasi UTS Semester {$smt}", 'Alur Dalam Semester');
                    }
                }
                continue;
            }

            // E. UAS Susulan
            if (stripos($upper, 'UAS SUSULAN') !== false) {
                if ($optAlurSiklus) {
                    $uts1 = $findName("/^UTS\s+SEMESTER\s+1$/i") ?: $findName("/^SPP\s+SEMESTER\s+1$/i");
                    if ($uts1) {
                        $addRule($n, $uts1, 'UAS Susulan wajib melunasi perkuliahan semester terkait', 'Alur Dalam Semester');
                    }
                }
                continue;
            }

            // F. SPP Bulanan (Contoh: SPP SEMESTER X BULAN Y atau SEMESTER X SPP BULAN Y)
            if (preg_match('/^(?:SPP\s+)?SEMESTER\s+(\d+)\s+(?:SPP\s+)?BULAN\s+(\d+)$/i', $n, $m)) {
                $smt = (int) $m[1];
                $bln = (int) $m[2];

                if ($bln > 1) {
                    if ($optSppBulanan) {
                        $prevBln = $bln - 1;
                        $prevBlnName = $findName("/^(?:SPP\s+)?SEMESTER\s+$smt\s+(?:SPP\s+)?BULAN\s+$prevBln$/i");
                        if ($prevBlnName) {
                            $addRule($n, $prevBlnName, "SPP Semester {$smt} Bulan {$bln} wajib melunasi Bulan {$prevBln}", 'Urutan SPP Bulanan');
                        }
                    }
                } else {
                    // Bulan 1
                    if ($smt == 1) {
                        $addRule($n, null, 'SPP Semester 1 Bulan 1 langsung dapat dibayarkan', 'Bebas / Tanpa Prasyarat');
                    } elseif ($optAntarSemester) {
                        $prevSmt = $smt - 1;
                        $prevSpp = $findName("/^(?:SPP\s+)?SEMESTER\s+$prevSmt$/i");
                        if ($prevSpp) {
                            $addRule($n, $prevSpp, "SPP Semester {$smt} Bulan 1 wajib melunasi SPP Semester {$prevSmt}", 'Urutan Antar Semester');
                        }
                    }
                }
                continue;
            }

            // G. SPP Semester (Full / Lump sum, contoh: SPP SEMESTER 1..8 atau SEMESTER 1..8)
            if (preg_match('/^(?:SPP\s+)?SEMESTER\s+(\d+)$/i', $n, $m)) {
                $smt = (int) $m[1];
                if ($smt == 1) {
                    $addRule($n, null, 'SPP Semester 1 langsung dapat dibayarkan tanpa prasyarat', 'Bebas / Tanpa Prasyarat');
                } else {
                    $reg = $optAlurSiklus ? $findName("/^(?:REGISTRASI|REGISTASI|HER+EGISTRASI|DAFTAR\s+ULANG)\s+SEMESTER\s+$smt$/i") : null;
                    if ($reg) {
                        $addRule($n, $reg, "SPP Semester {$smt} wajib melunasi Registrasi / Daftar Ulang Semester {$smt}", 'Alur Dalam Semester');
                    } elseif ($optAntarSemester) {
                        $prevSmt = $smt - 1;
                        $prevSpp = $findName("/^(?:SPP\s+)?SEMESTER\s+$prevSmt$/i");
                        if ($prevSpp) {
                            $addRule($n, $prevSpp, "SPP Semester {$smt} wajib melunasi SPP Semester {$prevSmt}", 'Urutan Antar Semester');
                        }
                    }
                }
                continue;
            }

            // H. Registrasi / Daftar Ulang / Herregistrasi
            if (preg_match('/^(?:REGISTRASI|REGISTASI|HER+EGISTRASI|DAFTAR\s+ULANG)\s+SEMESTER\s+(\d+)$/i', $n, $m)) {
                $smt = (int) $m[1];
                if ($smt <= 1) {
                    $addRule($n, null, 'Registrasi Semester 1 langsung dapat dibayarkan', 'Bebas / Tanpa Prasyarat');
                } elseif ($optAntarSemester) {
                    $prevSmt = $smt - 1;
                    $prevPrereq = $findName("/^UAS\s+SEMESTER\s+$prevSmt$/i")
                        ?: $findName("/^(?:SPP\s+)?SEMESTER\s+$prevSmt$/i")
                        ?: $findName("/^(?:REGISTRASI|REGISTASI|HER+EGISTRASI|DAFTAR\s+ULANG)\s+SEMESTER\s+$prevSmt$/i")
                        ?: $findName("/^(?:REGISTRASI|REGISTASI|HER+EGISTRASI|DAFTAR\s+ULANG)\s+SEMESTER\s+".($smt - 2)."$/i")
                        ?: $findName("/^UAS\s+SEMESTER\s+8$/i")
                        ?: $findName("/^(?:SPP\s+)?SEMESTER\s+8$/i");
                    if ($prevPrereq) {
                        $addRule($n, $prevPrereq, "Registrasi Semester {$smt} wajib melunasi kewajiban semester sebelumnya", 'Urutan Antar Semester');
                    }
                }
                continue;
            }

            // I. Heregistrasi Angkatan (misal HEREGISTRASI ANGKATAN 2020)
            if (stripos($upper, 'HEREGISTRASI ANGKATAN') !== false) {
                $addRule($n, null, 'Heregistrasi angkatan dapat langsung dibayar', 'Bebas / Tanpa Prasyarat');
                continue;
            }

            // J. KKN / PPL / PKL
            if (stripos($upper, 'KKN') !== false || stripos($upper, 'PPL') !== false || stripos($upper, 'PKL') !== false) {
                $spp6 = $findName("/^(?:SPP\s+)?SEMESTER\s+6$/i");
                if ($spp6) {
                    $addRule($n, $spp6, 'KKN/PKL/PPL wajib telah menyelesaikan perkuliahan sampai Semester 6', 'Kegiatan & Ujian Khusus');
                }
                continue;
            }

            // K. Uji Kompetensi
            if (stripos($upper, 'KOMPETENSI') !== false) {
                $spp8 = $findName("/^(?:SPP\s+)?SEMESTER\s+8$/i");
                if ($spp8) {
                    $addRule($n, $spp8, 'Uji Kompetensi diselesaikan di semester akhir', 'Kegiatan & Ujian Khusus');
                }
                continue;
            }

            // L. Cicilan Wasathiyah
            if (stripos($upper, 'CICILAN WASATHIYAH') !== false) {
                $was1 = $findName("/^PEMBAYARAN WASATHIYAH$/i");
                if ($was1) {
                    $addRule($n, $was1, 'Cicilan Wasathiyah lanjutan wajib menyelesaikan pembayaran awal', 'Kegiatan & Ujian Khusus');
                }
                continue;
            }

            // M. Skripsi
            if (stripos($upper, 'SKRIPSI') !== false) {
                if ($optAkhirWisuda) {
                    $spp7 = $findName("/^(?:SPP\s+)?SEMESTER\s+7$/i");
                    if ($spp7) {
                        $addRule($n, $spp7, 'Skripsi wajib telah menyelesaikan SPP perkuliahan semester sebelumnya', 'Tagihan Akhir & Wisuda');
                    }
                }
                continue;
            }

            // N. Sumbangan Pendidikan & Perpustakaan
            if (stripos($upper, 'SUMBANGAN') !== false) {
                if ($optAkhirWisuda) {
                    $spp8 = $findName("/^(?:SPP\s+)?SEMESTER\s+8$/i");
                    if ($spp8) {
                        $addRule($n, $spp8, 'Sumbangan wajib diselesaikan di akhir masa studi sebelum Wisuda', 'Tagihan Akhir & Wisuda');
                    }
                }
                continue;
            }

            // O. Wisuda
            if (stripos($upper, 'WISUDA') !== false) {
                if ($optAkhirWisuda) {
                    $skripsi = $findName("/^SKRIPSI$/i");
                    $uas8 = $findName("/^UAS\s+(?:SEMESTER\s+)?8$/i");

                    if ($skripsi) {
                        $addRule($n, $skripsi, 'Wisuda wajib telah menyelesaikan Skripsi', 'Tagihan Akhir & Wisuda');
                    } elseif ($uas8) {
                        $addRule($n, $uas8, 'Wisuda wajib telah melunasi seluruh kewajiban semester akhir', 'Tagihan Akhir & Wisuda');
                    }
                }
                continue;
            }

            // P. Tagihan awal murni bebas (Almamater, Pendaftaran, PMB, Pembayaran Wasathiyah, Paspor, Penunjang Pendidikan)
            $addRule($n, null, 'Tagihan awal / bebas tanpa prasyarat (langsung dapat dibayar)', 'Bebas / Tanpa Prasyarat');
        }

        // Q. Final Safety Net: Seluruh nama tagihan yang belum terdaftar sebagai target
        $alreadyTargets = collect($rules)->pluck('tagihan_nama')->map(fn ($nm) => strtolower(trim($nm)))->unique()->toArray();
        $alreadyTargetSet = array_flip($alreadyTargets);

        foreach ($names as $n) {
            $clean = trim((string) $n);
            if (! isset($alreadyTargetSet[strtolower($clean)])) {
                $addRule(
                    $clean,
                    null,
                    'Bebas tanpa prasyarat (langsung dapat dibayar)',
                    'Bebas / Tanpa Prasyarat'
                );
            }
        }

        return array_values($rules);
    }

    /**
     * Preview template rules summary and details.
     */
    public function preview(array $options = []): array
    {
        $rules = $this->generateTemplateRules($options);
        $collection = collect($rules);

        $byCategory = $collection->groupBy('kategori')->map(function ($items, $cat) {
            return [
                'kategori' => $cat,
                'count' => count($items),
                'samples' => $items->take(5)->values()->all(),
            ];
        })->values()->all();

        return [
            'total_rules' => count($rules),
            'categories' => $byCategory,
            'rules' => $rules,
        ];
    }

    /**
     * Apply template rules to database.
     */
    public function apply(array $options = [], bool $replaceExisting = false): array
    {
        $rules = $this->generateTemplateRules($options);

        if ($replaceExisting) {
            KeuanganSyaratTagihan::query()->delete();
        }

        $created = 0;
        $updated = 0;

        foreach ($rules as $r) {
            // Jika aturan ini memiliki syarat konkret, bersihkan aturan null lama untuk target ini
            if ($r['syarat_nama'] !== null) {
                KeuanganSyaratTagihan::where('tagihan_nama', $r['tagihan_nama'])->whereNull('syarat_nama')->delete();
            }

            $query = KeuanganSyaratTagihan::where('tagihan_nama', $r['tagihan_nama']);
            if ($r['syarat_nama'] === null) {
                $query->whereNull('syarat_nama');
            } else {
                $query->where('syarat_nama', $r['syarat_nama']);
            }
            $existing = $query->first();

            if ($existing) {
                $existing->update([
                    'keterangan' => $r['keterangan'],
                    'is_active' => true,
                ]);
                $updated++;
            } else {
                KeuanganSyaratTagihan::create([
                    'tagihan_nama' => $r['tagihan_nama'],
                    'syarat_nama' => $r['syarat_nama'],
                    'keterangan' => $r['keterangan'],
                    'is_active' => true,
                ]);
                $created++;
            }
        }

        return [
            'total_applied' => count($rules),
            'created' => $created,
            'updated' => $updated,
            'replace_existing' => $replaceExisting,
        ];
    }
}
