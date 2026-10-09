<?php
/*****************************************
  *----------------------------------
  |  ThisScriptVersion: 1.1.0     |
  |  © 2026 By PusyuuWanko/       |
  |  License: MIT License         |
  |  usage_tracker (共有ユーティリティ) |
----------------------------------*
  各プロダクト(main/toolbox/pips等の独立サイト)がページを読み込むたびに
  最終アクセス日時と日別アクセス件数を記録するための共通スクリプト。
  oppai管理パネルのダッシュボードで「何時間前に使われたか」の表示や、
  直近のアクセス推移グラフの表示に使う。
  呼び出し元の階層に依存しないよう、記録先パスは常にこのファイル基準
  (__DIR__)で解決する。

  1件ずつのページ表示ごとにファイルI/Oが発生するため、フォーマットは
  常に1ファイル・1回のflockで完結するようにしている(last日時とhistoryを
  別ファイルに分けるとロック回数が倍になるため)。
******************************************/

define('PUSYUU_PRODUCT_USAGE_PATH', __DIR__ . '/../../documents/productUsage.json');
define('PUSYUU_USAGE_HISTORY_DAYS', 30); // 何日分のhistoryを保持するか

/**
 * 指定したプロダクトキーの最終アクセス日時と、当日分のアクセス件数を記録する。
 * 保存形式: { "<productKey>": { "last": "2026-08-22T13:45:00", "history": { "2026-08-22": 12, ... } } }
 * 複数プロダクトから同時に呼ばれてもファイルが壊れないよう、
 * 読み取り→書き込みの間はflockで排他制御する。
 * 記録に失敗しても呼び出し元のページ表示には一切影響させない
 * (例外を投げない・警告も出さない)。
 */
function pusyuuRecordProductUsage(string $productKey): void {
  // 【記録できない事情は、すべてここで一度に見分ける】
  // 記録先を開けない・鍵を取れない、といった事情はどれも「今回は記録しない」という
  // 同じ結末になります。以前は見つけた場所ごとにreturnしていたため、後始末
  // (fclose)が枝ごとに散らばり、1つ書き忘れるとファイルを掴んだまま帰る形でした。
  // 開けたかどうかで先に分けておけば、後始末を書く場所は1箇所で済みます。
  $fp = ($productKey === '') ? false : @fopen(PUSYUU_PRODUCT_USAGE_PATH, 'c+');

  if ($fp === false) {
    // 記録先を開けなかった(または記録するキーが無い)。
    // 記録はページ表示のついでに過ぎないので、何も言わずに諦めます。
  } else if (!flock($fp, LOCK_EX)) {
    // 他のプロダクトが書いている最中。待たずに諦めます。
    // ここで待つと、記録のためにページの表示が止まります。
    fclose($fp);
  } else {
    $size = filesize(PUSYUU_PRODUCT_USAGE_PATH);
    $raw  = $size > 0 ? fread($fp, $size) : '';
    $data = json_decode((string)$raw, true);

    if (!is_array($data)) {
      $data = [];
    }

    // このプロダクトの欄を、必ず ['last'=>..., 'history'=>[...]] の形に整えます。
    // 旧形式(値が日時文字列そのもの)の場合はlastだけ引き継ぎ、historyは0から始めます。
    if (isset($data[$productKey]) && !is_array($data[$productKey])) {
      $data[$productKey] = ['last' => (string)$data[$productKey], 'history' => []];
    } else if (!isset($data[$productKey]) || !is_array($data[$productKey])) {
      $data[$productKey] = ['last' => '', 'history' => []];
    } else if (!isset($data[$productKey]['history']) || !is_array($data[$productKey]['history'])) {
      $data[$productKey]['history'] = [];
    }

    $today = date('Y-m-d');
    $data[$productKey]['last'] = date('Y-m-d\TH:i:s');
    $data[$productKey]['history'][$today] = (int)($data[$productKey]['history'][$today] ?? 0) + 1;

    // 保持日数を超えた古い日付は掃除する(ファイル肥大化防止)
    $cutoff = date('Y-m-d', time() - PUSYUU_USAGE_HISTORY_DAYS * 86400);
    foreach ($data[$productKey]['history'] as $day => $count) {
      if ($day < $cutoff) {
        unset($data[$productKey]['history'][$day]);
      }
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
  }
}
