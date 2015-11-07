<?php
/*
* This file is part of EC-CUBE
*
* Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
* http://www.lockon.co.jp/
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace DoctrineMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20150911190000 extends AbstractMigration
{

    public function up(Schema $schema)
    {
        $this->createShipNumberTable($schema);
    }

    public function down(Schema $schema)
    {
        $schema->dropTable('plg_ship_number_csv');

        // 送信履歴で使用するため、削除できない
        //$this->connection->delete('dtb_mail_template', array('name' => '発送メール'));
    }

    public function postUp(Schema $schema)
    {

      $app = new \Eccube\Application();
      $app->boot();

      $statement = $this->connection->prepare('SELECT template_id FROM dtb_mail_template');
      $statement->execute();
      $templateId = $statement->fetchAll();

      $templateIdNumber = count($templateId) + 1;

      $creatorId = '1';
      $name = '発送メール';
      $fileName = 'Mail/order.twig';
      $subject = '商品を発送致しました。';
      $header = 'この度はご注文いただき誠にありがとうございます。

ご注文いただいた下記商品を本日発送いたしました。
伝票番号：
発送業者：ヤマト運輸
詳しい発送状況は、荷物お問い合わせページ（ヤマト運輸） からご確認ください。
http://toi.kuronekoyamato.co.jp/cgi-bin/tneko';
      $footer = '============================================


このメッセージはお客様へのお知らせ専用ですので、
このメッセージへの返信としてご質問をお送りいただいても回答できません。
ご了承ください。

ご質問やご不明な点がございましたら、こちらからお願いいたします。

';
      $delFlg = '0';
      $datetime = date('Y-m-d H:i:s');
      $insert = "INSERT INTO dtb_mail_template(
                          template_id, creator_id, name, file_name, subject, header, footer, del_flg, create_date, update_date)
                  VALUES ('$templateIdNumber', '$creatorId', '$name', '$fileName', '$subject', '$header', '$footer', '$delFlg', '$datetime', '$datetime'
                          );";
      $this->connection->executeUpdate($insert);

    }


    protected function createShipNumberTable(Schema $schema)
    {
        $table = $schema->createTable("plg_ship_number_csv");
        $table->addColumn('order_id', 'integer');
        $table->addColumn('ship_number', 'text', array(
          'notnull' => false,
        ));
    }
}
