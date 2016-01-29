<?php
/*
* This file is part of EC-CUBE
*
* Copyright(c) 2015 Takashi Otaki All Rights Reserved.
* 
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

      $statement = $this->connection->prepare('SELECT template_id FROM dtb_mail_template ORDER BY template_id desc');
      $statement->execute();
      $templateId = $statement->fetchColumn();

      $this->connection->insert('dtb_mail_template', array(
        'template_id' => $templateId + 1,
        'creator_id' => '1',
        'name' => '発送メール',
        'file_name' => 'Mail/order.twig',
        'subject' => '商品を発送致しました。',
        'header' => 'この度はご注文いただき誠にありがとうございます。

ご注文いただいた下記商品を本日発送いたしました。
伝票番号：
発送業者：ヤマト運輸
詳しい発送状況は、荷物お問い合わせページ（ヤマト運輸） からご確認ください。
http://toi.kuronekoyamato.co.jp/cgi-bin/tneko',
        'footer' => '============================================


このメッセージはお客様へのお知らせ専用ですので、
このメッセージへの返信としてご質問をお送りいただいても回答できません。
ご了承ください。

ご質問やご不明な点がございましたら、こちらからお願いいたします。

',
        'del_flg' => '0',
        'create_date' => date('Y-m-d H:i:s'),
        'update_date' => date('Y-m-d H:i:s')
      ));

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
