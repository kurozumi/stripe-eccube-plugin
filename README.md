# Stripe for EC-CUBE4

EC-CUBE4でStripeでクレジットカード決済ができるプラグインのサンプルです。  
非公式プラグインですのでご利用は自己責任でお願い致します。  


## インストールと有効化

```
bin/console eccube:composer:require stripe/stripe-php

bin/console eccube:plugin:install --code PayJP
bin/console eccube:plugin:enable --code PayJP
```

## シークレットキーと公開キーを設定

Stripeのアカウントを取得して秘密鍵と公開鍵を以下のファイルに設定してください。

```
Plugin/Stripe/Resource/config/services.yaml
```

## Shopping/index.twigにタグを追記

Shopping/index.twigに以下のタグを追記してください。

```
{{ include('@Stripe/credit.twig', ignore_missing=true) }}
```

以上で設定は終了です。
お疲れさまでした。


あとは配送方法設定で取り扱う支払い方法にStripeを追加してあげてください。
