# Stripe for EC-CUBE4.2

EC-CUBE4でStripeでクレジットカード決済ができるプラグインのサンプルです。
非公式プラグインですのでご利用は自己責任でお願い致します。


## インストールと有効化

#### ライブラリをインストール
```
composer require stripe/stripe-php
```

#### EC-CUBEの認証キーを設定している場合のライブラリのインストール方法
```
bin/console eccube:composer:require stripe/stripe-php
```

#### GitHubからプラグインをクローン
```
git clone git@github.com:kurozumi/stripe-eccube-plugin.git app/Plugin
```

#### プラグインのインストールと有効化
```
bin/console eccube:plugin:install --code Stripe4
bin/console eccube:plugin:enable --code Stripe4
```

## シークレットキーと公開キーを設定

Stripeのアカウントを取得して管理画面でAPIキーを設定してください。

## Shopping/index.twigにタグを追記

Shopping/index.twigの支払い方法の下に以下のタグを追記してください。

```
{{ include('@Stripe4/credit.twig', ignore_missing=true) }}
```

以上で設定は終了です。
お疲れさまでした。


あとは配送方法設定で取り扱う支払い方法にStripeを追加してあげてください。

## 決済テスト用クレジットカード

#### 通常のクレジットカード番号
```
4242 4242 4242 4242
```

#### 3Dセキュア認証が必要なクレジットカード番号
```
4000 0000 0000 3220
```

