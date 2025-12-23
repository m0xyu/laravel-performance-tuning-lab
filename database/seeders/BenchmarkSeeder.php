<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Shop;
use App\Models\Scout;
use App\Models\Tag;
use App\Models\AccessLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BenchmarkSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('拡張ベンチマーク用データの生成を開始します...');

        Schema::disableForeignKeyConstraints();
        Tag::truncate();
        DB::table('shop_tag')->truncate(); // 中間テーブル
        AccessLog::truncate();
        Shop::truncate();
        Scout::truncate();
        User::truncate();
        Schema::enableForeignKeyConstraints();

        // 1. タグマスタの生成（20種類）
        $tags = Tag::factory()->count(20)->create();

        $chunkSize = 500; // 処理が重くなるので少し減らす
        $total = 10000; // 合計生成数

        for ($i = 0; $i < $total; $i += $chunkSize) {
            DB::transaction(function () use ($chunkSize, $tags) {
                // ユーザー生成
                $users = User::factory()->count($chunkSize)->create();

                // バルクインサート用の配列
                $accessLogsBuffer = [];
                $shopTagsBuffer = [];

                foreach ($users as $user) {
                    // ショップ生成
                    $shop = Shop::factory()->create([
                        'user_id' => $user->id,
                        'created_at' => now()->subDays(rand(1, 365)),
                    ]);

                    // スカウト生成（ランダム）
                    if (rand(0, 1)) {
                        Scout::factory()->count(rand(1, 3))->create(['user_id' => $user->id]);
                    }

                    // タグ付け（中間テーブル用データ）: ランダムに2〜4個
                    $randomTags = $tags->random(rand(2, 4));
                    foreach ($randomTags as $tag) {
                        $shopTagsBuffer[] = [
                            'shop_id' => $shop->id,
                            'tag_id' => $tag->id,
                        ];
                    }

                    // アクセスログ生成: ランダムに0〜50件
                    $logCount = rand(0, 50);
                    for ($k = 0; $k < $logCount; $k++) {
                        $accessLogsBuffer[] = [
                            'shop_id' => $shop->id,
                            'accessed_at' => now()->subDays(rand(0, 30))->format('Y-m-d H:i:s'),
                        ];
                    }
                }

                if (!empty($shopTagsBuffer)) {
                    DB::table('shop_tag')->insert($shopTagsBuffer);
                }
                if (!empty($accessLogsBuffer)) {
                    AccessLog::insert($accessLogsBuffer);
                }
            });

            $this->command->info("{$chunkSize} 件のショップと関連データを生成完了... (" . ($i + $chunkSize) . "/{$total})");
        }

        $this->command->info('データ生成完了。');
    }
}
