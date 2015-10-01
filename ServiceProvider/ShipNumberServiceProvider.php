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

namespace Plugin\ShipNumber\ServiceProvider;

use Eccube\Application;
use Silex\Application as BaseApplication;
use Silex\ServiceProviderInterface;

class ShipNumberServiceProvider implements ServiceProviderInterface
{
    public function register(BaseApplication $app)
    {

        // Formの定義
        $app['form.type.extensions'] = $app->share($app->extend('form.type.extensions', function ($extensions) use ($app) {
            $extensions[] = new \Plugin\ShipNumber\Form\Extension\Admin\ShipNumberCollectionExtension();

            return $extensions;
        }));

        //Repository
        $app['eccube.plugin.repository.ship_number'] = $app->share(function () use ($app) {
            return $app['orm.em']->getRepository('\Plugin\ShipNumber\Entity\ShipNumber');
        });



        //Controllerの追加
        $app->match('/' . $app["config"]["admin_route"] . '/order/shipping_number', '\\Plugin\\ShipNumber\\Controller\\ShipNumberController::index')
            ->bind('admin_shipping_number');


        // メニュー登録
        $app['config'] = $app->share($app->extend('config', function ($config) {
            $addNavi['id'] = "shipping_number";
            $addNavi['name'] = "配送伝票番号CSV登録";
            $addNavi['url'] = "admin_shipping_number";

            $nav = $config['nav'];
            foreach ($nav as $key => $val) {
                if ("order" == $val["id"]) {
                    $nav[$key]['child'][] = $addNavi;
                }
            }

            $config['nav'] = $nav;
            return $config;
        }));

        //テンプレートダウンロード用
        $app->match('/' . $app["config"]["admin_route"] . '/order/shipping_number_csv_template', '\\Plugin\\ShipNumber\\Controller\\ShipNumberController::csvTemplate')->bind('admin_shipnumber_csv_template');


    }

    public function boot(BaseApplication $app)
    {
    }
}
