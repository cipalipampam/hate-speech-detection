<?php

namespace Database\Seeders;

use App\Models\Analysis;
use App\Models\AnalysisStatistic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoAnalysisSeeder extends Seeder
{
    /**
     * Buat data analisis demo agar dashboard tidak kosong saat presentasi.
     * Schema disesuaikan dengan migration aktual (2026_08_28).
     */
    public function run(): void
    {
        $analyst = User::where('email', 'analyst@hatespeech.test')->first();
        $admin   = User::where('email', 'admin@hatespeech.test')->first();

        if (!$analyst || !$admin) {
            $this->command->warn('User demo tidak ditemukan. Jalankan RoleAndPermissionSeeder terlebih dahulu.');
            return;
        }

        // ── Sesi 1: Analisis RUU Pilkada (215 data, 66% hate) ─────────────────
        $this->createAnalysis(
            user: $analyst,
            title: 'Analisis Sentimen RUU Pilkada 2026',
            platform: 'both',
            keywords: ['RUU Pilkada', 'Pilkada 2026', 'DPR'],
            jobId: 'demo-job-001',
            execTime: 847,
            totalData: 215,
            hateCount: 142,
            platforms: ['X', 'Threads'],
            level2Breakdown: [
                'delegitimasi_institusi' => 64,
                'dehumanisasi'           => 22,
                'ajakan_kekerasan'       => 18,
                'hoax_pemicu_kebencian'  => 21,
                'kutukan_agama_personal' => 10,
                'tidak_relevan'          => 7,
            ],
            platformBreakdown: ['X' => 130, 'Threads' => 85],
            daysAgo: 5,
            withExport: true,
        );

        // ── Sesi 2: Ujaran Kebencian Harga Sembako (98 data, 55% hate) ─────────
        $this->createAnalysis(
            user: $analyst,
            title: 'Ujaran Kebencian Terkait Harga Sembako',
            platform: 'x',
            keywords: ['sembako mahal', 'harga naik', 'pemerintah gagal'],
            jobId: 'demo-job-002',
            execTime: 412,
            totalData: 98,
            hateCount: 54,
            platforms: ['X'],
            level2Breakdown: [
                'delegitimasi_institusi' => 32,
                'dehumanisasi'           => 8,
                'ajakan_kekerasan'       => 4,
                'hoax_pemicu_kebencian'  => 7,
                'kutukan_agama_personal' => 2,
                'tidak_relevan'          => 1,
            ],
            platformBreakdown: ['X' => 98, 'Threads' => 0],
            daysAgo: 2,
            withExport: true,
        );

        // ── Sesi 3: Deteksi Hate Speech Isu Keagamaan (47 data, 40% hate) ──────
        $this->createAnalysis(
            user: $admin,
            title: 'Deteksi Hate Speech Isu Keagamaan',
            platform: 'threads',
            keywords: ['agama', 'toleransi', 'SARA'],
            jobId: 'demo-job-003',
            execTime: 198,
            totalData: 47,
            hateCount: 19,
            platforms: ['Threads'],
            level2Breakdown: [
                'delegitimasi_institusi' => 5,
                'dehumanisasi'           => 4,
                'ajakan_kekerasan'       => 2,
                'hoax_pemicu_kebencian'  => 3,
                'kutukan_agama_personal' => 4,
                'tidak_relevan'          => 1,
            ],
            platformBreakdown: ['X' => 0, 'Threads' => 47],
            daysAgo: 0,
            withExport: false,
        );

        $total = 215 + 98 + 47;
        $this->command->info("✓ Demo analyses seeded: 3 sesi dengan total {$total} postingan.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    private function createAnalysis(
        User   $user,
        string $title,
        string $platform,
        array  $keywords,
        string $jobId,
        int    $execTime,
        int    $totalData,
        int    $hateCount,
        array  $platforms,
        array  $level2Breakdown,
        array  $platformBreakdown,
        int    $daysAgo,
        bool   $withExport,
    ): void {
        // Cegah duplikasi
        if (Analysis::where('title', $title)->exists()) {
            $this->command->line("  Skipped (sudah ada): {$title}");
            return;
        }

        $analysis = Analysis::create([
            'user_id'                => $user->id,
            'title'                  => $title,
            'platform'               => $platform,
            'keywords'               => $keywords,
            'search_mode'            => 'latest',
            'max_links'              => $totalData + 20,
            'max_scroll_steps'       => 300,
            'headless'               => true,
            'status'                 => 'completed',
            'job_id'                 => $jobId,
            'execution_time_seconds' => $execTime,
            'created_at'             => now()->subDays($daysAgo)->subHours(2),
            'updated_at'             => now()->subDays($daysAgo),
        ]);

        // ── Statistics ─────────────────────────────────────────────────────────
        $nonHateCount = $totalData - $hateCount;
        AnalysisStatistic::create([
            'analysis_id'          => $analysis->id,
            'total_data'           => $totalData,
            'hate_speech_count'    => $hateCount,
            'non_hate_speech_count'=> $nonHateCount,
            'hate_speech_pct'      => round(($hateCount / $totalData) * 100, 2),
            'avg_confidence_lvl1'  => 0.8750,
            'avg_confidence_lvl2'  => 0.8120,
            'level2_breakdown'     => $level2Breakdown,
            'platform_breakdown'   => $platformBreakdown,
        ]);

        // ── Posts & Classifications ────────────────────────────────────────────
        $this->seedPosts($analysis, $totalData, $hateCount, $platforms);

        // ── Export ────────────────────────────────────────────────────────────
        if ($withExport) {
            DB::table('analysis_exports')->insert([
                'analysis_id' => $analysis->id,
                'filename'    => 'analisis-demo-' . $jobId . '.csv',
                'disk'        => 'local',
                'path'        => 'exports/demo-' . $jobId . '.csv',
                'format'      => 'csv',
                'size_bytes'  => rand(15000, 60000),
                'mime_type'   => 'text/csv',
                'created_at'  => now()->subDays($daysAgo),
                'updated_at'  => now()->subDays($daysAgo),
            ]);
        }

    }

    private function seedPosts(Analysis $analysis, int $total, int $hateCount, array $platforms): void
    {
        $hateSamples = [
            'Dasar pejabat tidak becus, merusak bangsa ini! Malu jadi warga negara!',
            'Mereka semua penghianat bangsa, harus diusir dari negeri ini!',
            'Pemerintah bodoh gak bisa kerja, bikin rakyat susah terus!',
            'Orang-orang ini layaknya binatang, tidak punya otak sama sekali!',
            'Ancaman bagi bangsa, lebih baik mereka disingkirkan saja!',
            'Dasar manusia tidak bermoral, merusak generasi muda kita!',
            'Mereka harus diadili! Penghancur tatanan negara ini!',
            'Goblok semua yang dukung kebijakan ini, tidak punya nurani!',
            'Pejabat korup ini harus dipermalukan, dibui seumur hidup!',
            'Tolol! Negara dijalankan oleh orang-orang tidak kompeten!',
        ];

        $nonHateSamples = [
            'Semoga kebijakan ini membawa kebaikan bagi masyarakat luas.',
            'Kita perlu berdialog dengan kepala dingin untuk menemukan solusi.',
            'Harapannya pemerintah bisa lebih memperhatikan rakyat kecil.',
            'Setuju dengan kebijakan ini, semoga implementasinya berjalan baik.',
            'Perlu kajian lebih mendalam sebelum kebijakan ini diterapkan.',
            'Mari kita dukung program pemerintah yang berpihak pada rakyat.',
            'Mudah-mudahan masalah ini segera terselesaikan dengan baik.',
            'Opini saya berbeda, tapi saya menghormati perbedaan pendapat.',
            'Ini adalah langkah maju yang perlu kita apresiasi bersama.',
            'Semoga para pemimpin kita selalu diberikan kebijaksanaan.',
        ];

        $level2Labels = [
            'delegitimasi_institusi',
            'dehumanisasi',
            'ajakan_kekerasan',
            'hoax_pemicu_kebencian',
            'kutukan_agama_personal',
            'tidak_relevan',
        ];

        $postRows           = [];
        $classificationRows = [];
        $now                = now()->toDateTimeString();

        for ($i = 0; $i < $total; $i++) {
            $isHate   = ($i < $hateCount);
            $platform = $platforms[array_rand($platforms)];

            // Simpan posts dalam batch
            $postId = DB::table('analysis_posts')->insertGetId([
                'analysis_id'     => $analysis->id,
                'platform'        => $platform,
                'source_url'      => 'https://' . strtolower($platform) . '.com/demo/post/' . uniqid(),
                'author_username' => 'user_demo_' . rand(100, 999),
                'post_type'       => 'Original Post',
                'post_date'       => now()->subDays(rand(0, 30))->format('Y-m-d'),
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            $rawContent = $isHate
                ? $hateSamples[$i % count($hateSamples)]
                : $nonHateSamples[$i % count($nonHateSamples)];

            $label1 = $isHate ? 'hate_speech' : 'non_hate_speech';
            $label2 = $isHate ? $level2Labels[array_rand($level2Labels)] : 'tidak_relevan';
            $conf1  = round(rand(72, 98) / 100, 4);
            $conf2  = $isHate ? round(rand(65, 95) / 100, 4) : 0.0000;

            DB::table('analysis_classifications')->insert([
                'post_id'         => $postId,
                'raw_content'     => $rawContent,
                'clean_content'   => strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $rawContent)),
                'label_lvl1'      => $label1,
                'confidence_lvl1' => $conf1,
                'label_lvl2'      => $label2,
                'confidence_lvl2' => $conf2,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }
}
