<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\ThAkademik;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function getAlumniByKota(Request $request)
    {
        $alumni = Alumni::where('kota', '!=', '')
            ->when($request->filled('th_akademik_id'), function ($query) use ($request) {
                $query->where('th_akademik_id', $request->th_akademik_id);
            })
            ->selectRaw('kota, COUNT(*) as jumlah')
            ->groupBy('kota')
            ->get();

        if ($alumni->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => 'Alumni by daerah not found',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'data'    => [
                'daerah' => $alumni->pluck('kota')->map(function ($item) {
                    $decoded = json_decode($item, true);
                    return $decoded['nama'] ?? null;
                }),
                'jumlah' => $alumni->pluck('jumlah'),
            ],
            'message' => 'Alumni by daerah retrieved successfully',
        ]);
    }

    public function getAlumniByProvinsi(Request $request)
    {
        $alumni = Alumni::where('provinsi', '!=', '')
            ->when($request->filled('th_akademik_id'), function ($query) use ($request) {
                $query->where('th_akademik_id', $request->th_akademik_id);
            })
            ->selectRaw('provinsi, COUNT(*) as jumlah')
            ->groupBy('provinsi')
            ->get();

        if ($alumni->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => 'Alumni by provinsi not found',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'data'    => [
                'daerah' => $alumni->pluck('provinsi')->map(function ($item) {
                    $decoded = json_decode($item, true);
                    return $decoded['nama'] ?? null;
                }),
                'jumlah' => $alumni->pluck('jumlah'),
            ],
            'message' => 'Alumni by provinsi retrieved successfully',
        ]);
    }

    public function getAlumniByNegara(Request $request)
    {
        $alumni = Alumni::where('negara', '!=', '')
            ->when($request->filled('th_akademik_id'), function ($query) use ($request) {
                $query->where('th_akademik_id', $request->th_akademik_id);
            })
            ->selectRaw('negara, COUNT(*) as jumlah')
            ->groupBy('negara')
            ->get();

        if ($alumni->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => 'Alumni by daerah not found',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'data'    => [
                'daerah' => $alumni->pluck('negara')->map(function ($item) {
                    $decoded = json_decode($item, true);
                    return $decoded['nama'] ?? null;
                }),
                'jumlah' => $alumni->pluck('jumlah'),
            ],
            'message' => 'Alumni by daerah retrieved successfully',
        ]);
    }

    public function getAlumniByDaerah(Request $request)
    {
        $request->validate([
            'daerah' => 'required',
        ]);

        $daerah = $request->daerah === 'kabupaten / kota'
        ? 'kota'
        : $request->daerah;

        $alumni = Alumni::where(function ($query) use ($daerah) {
            $query->where($daerah, '!=', '')
                ->where($daerah, '!=', 'null');
        })
            ->whereNotNull($daerah)
            ->when($request->filled('th_akademik_id'), function ($query) use ($request) {
                $query->where('th_akademik_id', $request->th_akademik_id);
            })
            ->selectRaw("$daerah, COUNT(*) as jumlah")
            ->groupBy($daerah)
            ->get();

        if ($alumni->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => 'Alumni by daerah not found',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'data'    => [
                'daerah' => $daerah == "negara" ?
                $alumni->pluck($daerah) :
                $alumni->pluck($daerah)->map(function ($item) {
                    $decoded = json_decode($item, true);
                    return $decoded['nama'] ?? null;
                }),
                'jumlah' => $alumni->pluck('jumlah'),
            ],
            'message' => 'Alumni by daerah retrieved successfully',
        ]);
    }

    public function getWidget()
    {
        $th_akademik = ThAkademik::where('aktif', 'Y')->first();
        if (! $th_akademik) {
            return response()->json([
                'status'  => false,
                'message' => 'ThAkademik not found',
            ], 404);
        }
        $dataCek = [
            'alumni.nama_lengkap',
            'alumni.no_hp',
            'alumni.alamat',
            // 'alumni.kota',
            'alumni.provinsi',
            'users.avatar',
        ];

        $user   = User::whereNot('role_id', 2)->count();
        $alumni = Alumni::where('th_akademik_id', $th_akademik->id)->count();
        $query  = Alumni::join('users', 'users.id', '=', 'alumni.user_id');
        foreach ($dataCek as $field) {
            $query->whereNotNull($field);
        }
        $query->where('alumni.th_akademik_id', $th_akademik->id);
        $alumniLengkap      = $query->count();
        $alumniBelumLengkap = $alumni - $alumniLengkap;

        $year         = Carbon::now()->year;
        $userLastYear = User::whereYear('created_at', $year)->whereNot('role_id', 2)->count();
        if ($userLastYear) {
            $yearBeforeLastYear = $year - 1;
            $userBeforeLastYear = User::whereYear('created_at', $th_akademik)->whereNot('role_id', 2)->count();
        } else {
            $userBeforeLastYear = 0;
        }
        $th_akademikBefore = ThAkademik::where('nama', ((int) $th_akademik->nama) - 1)->first();
        if ($th_akademikBefore) {
            $alumniBefore = Alumni::where('th_akademik_id', $th_akademikBefore->id)->count();
            $query        = Alumni::join('users', 'users.id', '=', 'alumni.user_id');
            foreach ($dataCek as $field) {
                $query->whereNotNull($field);
            }
            $query->where('alumni.th_akademik_id', $th_akademikBefore->id);
            $alumniLengkapBefore     = $query->count();
            $alumniBelumLengkaBefore = $alumniBefore - $alumniLengkapBefore;
        } else {
            $alumniBefore            = 0;
            $alumniLengkapBefore     = 0;
            $alumniBelumLengkaBefore = 0;
        }

        return response()->json([
            'status'  => true,
            'data'    => [
                [
                    "icon"    => 'ri-user-line',
                    "color"   => 'primary',
                    "title"   => 'Data User',
                    "value"   => $user,
                    "change"  => $userLastYear - $userBeforeLastYear >= 0 ? '+' . ($userLastYear - $userBeforeLastYear) : '-' . ($userBeforeLastYear - $userLastYear),
                    "isHover" => false,
                ],
                [
                    "icon"    => 'ri-graduation-cap-line',
                    "color"   => 'warning',
                    "title"   => 'Data Alumni',
                    "value"   => $alumni,
                    "change"  => $alumni - $alumniBefore >= 0 ? '+' . ($alumni - $alumniBefore) : '-' . ($alumniBefore - $alumni),
                    "isHover" => false,
                ],
                [
                    "icon"    => 'ri-check-line',
                    "color"   => 'success',
                    "title"   => 'Alumni Data Lengkap',
                    "value"   => $alumniLengkap,
                    "change"  => $alumniLengkap - $alumniLengkapBefore >= 0 ? '+' . ($alumniLengkap - $alumniLengkapBefore) : '-' . ($alumniLengkapBefore - $alumniLengkap),
                    "isHover" => false,
                ],
                [
                    "icon"    => 'ri-close-line',
                    "color"   => 'error',
                    "title"   => 'Alumni Data Belum Lengkap',
                    "value"   => $alumniBelumLengkap,
                    "change"  => $alumniBelumLengkap - $alumniBelumLengkaBefore >= 0 ? '+' . ($alumniBelumLengkap - $alumniBelumLengkaBefore) : '-' . ($alumniBelumLengkaBefore - $alumniBelumLengkap),
                    "isHover" => false,
                ],
            ],
            'message' => 'Widget data retrieved successfully',
        ]);
    }

    public function tableOverview(Request $request)
    {
        $query = Alumni::join('users', 'users.id', '=', 'alumni.user_id')
            ->select(
                'alumni.*',
                \DB::raw("CONCAT('" . asset('avatar/') . "/', users.avatar) as avatar_url"),
                'users.jenis_kelamin',
            );

        if ($request->filled('nama_lengkap')) {
            $query->where('alumni.nama_lengkap', 'like', '%' . $request->nama_lengkap . '%');
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->orWhere('alumni.nama_lengkap', 'LIKE', "%$request->search%");
                $q->orWhere('alumni.kota', 'LIKE', "%$request->search%");
                $q->orWhere('alumni.alamat', 'LIKE', "%$request->search%");
            });
        }

        $dataCek = [
            'alumni.nama_lengkap',
            'alumni.no_hp',
            'alumni.alamat',
            'alumni.kota',
            'alumni.provinsi',
            'users.avatar',
        ];

        $query->where(function ($q) use ($dataCek) {
            foreach ($dataCek as $field) {
                $q->orWhereNull($field);
            }
        });

        $query->where('alumni.th_akademik_id', $request->th_akademik_id ?? ThAkademik::where('aktif', 'Y')->first()->id);
        // Sorting
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc'); // 'asc' or 'desc'

        $query->orderBy($sortKey, $sortOrder);

        $alumni = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $alumni,
            'message' => 'Alumni retrieved successfully',
            'request' => $request->all(),
        ]);
    }
}
