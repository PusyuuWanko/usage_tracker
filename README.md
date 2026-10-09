# usage_tracker

ページが読み込まれるたびに、プロダクトごとの最終アクセス日時と日別アクセス件数を記録する共通スクリプトです。
管理画面で「何時間前に使われたか」や、直近のアクセス推移を表示するために使います。

## 使い方

```php
require_once __DIR__ . '/usage_tracker.php';
pusyuuRecordProductUsage('main');  // プロダクトキー
```

記録に失敗しても、例外や警告を出さずに呼び出し元のページ表示を続けます。
複数のプロダクトから同時に呼ばれても壊れないよう、1ファイル・1回の `flock` で書き込みます。

## 保存形式

```json
{
  "main": {
    "last": "2026-08-22T13:45:00",
    "history": { "2026-08-22": 12, "2026-08-21": 30 }
  }
}
```

## 設定

| 定数 | 値 | 内容 |
| --- | --- | --- |
| `PUSYUU_PRODUCT_USAGE_PATH` | `__DIR__ . '/../../documents/productUsage.json'` | 保存先 |
| `PUSYUU_USAGE_HISTORY_DAYS` | `30` | history を何日分残すか |

## ライセンス

[MIT License](LICENSE) © 2026 ISAMI ABE
