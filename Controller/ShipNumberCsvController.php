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

namespace Plugin\ShipNumberCsv\Controller;

use Eccube\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception as HttpException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Eccube\Exception\CsvImportException;
use Eccube\Service\CsvImportService;
use Eccube\Util\Str;
use Symfony\Component\Filesystem\Filesystem;

class ShipNumberCsvController
{

    private $errors = array();
    private $shipnumberTwig = 'ShipNumberCsv/Resource/template/Admin/csv_shipnumber.twig';

    public function __construct()
    {
    }

    public function index(Application $app, Request $request)
    {

      $form = $app['form.factory']->createBuilder('admin_csv_import')->getForm();
      $headers = $this->getShipNumberCsvHeader();

      if ('POST' === $request->getMethod()) {

        $form->handleRequest($request);

        if ($form->isValid()) {

          $formFile = $form['import_file']->getData();

          if (!empty($formFile)) {

            $data = $this->getImportData($app, $formFile);

            $keys = array_keys($headers);
            $columnHeaders = $data->getColumnHeaders();

            if ($keys !== $columnHeaders) {
                $this->addErrors('CSVのフォーマットが一致しません。');
                return $this->render($app, $form, $headers, $this->shipnumberTwig);
            }

            $size = count($data);
            if ($size < 1) {
                $this->addErrors('CSVデータが存在しません。');
                return $this->render($app, $form, $headers, $this->shipnumberTwig);
            }

            $headerSize = count($keys);

            $this->em = $app['orm.em'];
            $this->em->getConfiguration()->setSQLLogger(null);

            $this->em->getConnection()->beginTransaction();


            // CSVファイルの登録処理
            foreach ($data as $row) {

                if ($headerSize != count($row)) {
                    $this->addErrors(($data->key() + 1) . '行目のCSVフォーマットが一致しません。');
                    return $this->render($app, $form, $headers, $this->shipnumberTwig);
                }


                if (Str::isBlank($row['注文番号'])) {
                    $this->addErrors(($data->key() + 1) . '行目の注文番号が設定されていません。');
                    return $this->render($app, $form, $headers, $this->shipnumberTwig);
                } else {
                  $Order = $app['eccube.repository.order']->find($row['注文番号']);
                  if (!$Order) {
                    $this->addErrors(($data->key() + 1) . '行目の注文番号が存在しません。');
                    return $this->render($app, $form, $headers, $this->shipnumberTwig);
                  } else {

                    $OrderContent = $app['eccube.plugin.repository.ship_number']->find($row['注文番号']);

                    if (is_null($OrderContent)) {
                        $OrderContent = new \Plugin\ShipNumberCsv\Entity\ShipNumberCsv();
                    }

                    $OrderContent->setOrderId(Str::trimAll($row['注文番号']));

                  }

                }

                if (Str::isBlank($row['配送伝票番号'])) {
                    $this->addErrors(($data->key() + 1) . '行目の配送伝票番号が設定されていません。');
                    return $this->render($app, $form, $headers, $this->shipnumberTwig);
                } else {
                    $OrderContent->setShipNumber(Str::trimAll($row['配送伝票番号']));

                }

                $OrderContent
                    ->setOrder($Order);

                $this->em->persist($OrderContent);

            }

            $this->em->flush();
            $this->em->getConnection()->commit();
            $this->em->close();

            $app->addSuccess('配送伝票番号登録CSVファイルをアップロードしました。', 'admin');

          }
        }
      }

      return $this->render($app, $form, $headers, $this->shipnumberTwig);

    }


    private function getShipNumberCsvHeader()
    {
    return array(
        '注文番号' => 'order_id',
        '配送伝票番号' => 'ship_number',
      );
    }


    /**
     * アップロード用CSV雛形ファイルダウンロード
     */
    public function csvTemplate(Application $app, Request $request)
    {
        set_time_limit(0);

        $response = new StreamedResponse();

        $headers = $this->getShipNumberCsvHeader();
        $filename = 'ship_number.csv';

        $response->setCallback(function () use ($app, $request, $headers) {

            // ヘッダ行の出力
            $row = array();
            foreach ($headers as $key => $value) {
                $row[] = mb_convert_encoding($key, $app['config']['csv_export_encoding'], 'UTF-8');
            }

            $fp = fopen('php://output', 'w');
            fputcsv($fp, $row, $app['config']['csv_export_separator']);
            fclose($fp);

        });

        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename=' . $filename);
        $response->send();

        return $response;
    }


    /**
     * 登録、更新時のエラー画面表示
     *
     */
    protected function render($app, $form, $headers, $twig)
    {

        if ($this->hasErrors()) {
            if ($this->em) {
                $this->em->getConnection()->rollback();
                $this->em->close();
            }
        }

        if (!empty($this->fileName)) {
            try {
                $fs = new Filesystem();
                $fs->remove($app['config']['csv_temp_realdir'] . '/' . $this->fileName);
            } catch (\Exception $e) {
                // エラーが発生しても無視する
            }
        }

        return $app->render($twig, array(
            'form' => $form->createView(),
            'headers' => $headers,
            'errors' => $this->errors,
        ));
    }


    /**
     * 登録、更新時のエラー画面表示
     *
     */
    protected function addErrors($message)
    {
        $e = new CsvImportException($message);
        $this->errors[] = $e;
    }

    /**
     * @return array
     */
    protected function getErrors()
    {
        return $this->errors;
    }


    /**
     *
     * @return boolean
     */
    protected function hasErrors()
    {
        return count($this->getErrors()) > 0;
    }


    /**
     * アップロードされたCSVファイルの行ごとの処理
     *
     * @param $formFile
     * @return CsvImportService
     */
    protected function getImportData($app, $formFile)
    {

        // アップロードされたCSVファイルを一時ディレクトリに保存
        $this->fileName = 'upload_' . Str::random() . '.' . $formFile->getClientOriginalExtension();
        $formFile->move($app['config']['csv_temp_realdir'], $this->fileName);

        $file = file_get_contents($app['config']['csv_temp_realdir'] . '/' . $this->fileName);
        // アップロードされたファイルがUTF-8以外は文字コード変換を行う
        $encode = Str::characterEncoding(substr($file, 0, 6));
        if ($encode != 'UTF-8') {
            $file = mb_convert_encoding($file, 'UTF-8', $encode);
        }
        $file = Str::convertLineFeed($file);

        $tmp = tmpfile();
        fwrite($tmp, $file);
        rewind($tmp);
        $meta = stream_get_meta_data($tmp);
        $file = new \SplFileObject($meta['uri']);

        set_time_limit(0);

        // アップロードされたCSVファイルを行ごとに取得
        $data = new CsvImportService($file, $app['config']['csv_import_delimiter'], $app['config']['csv_import_enclosure']);

        $data->setHeaderRowNumber(0);

        return $data;
    }


}
