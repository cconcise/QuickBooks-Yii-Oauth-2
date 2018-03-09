<?php

namespace app\controllers;

use app\components\Quickbooks;
use Yii;
use yii\helpers\VarDumper;
use yii\web\Controller;
use yii\db\IntegrityException;
use yii\helpers\Url;

class QuickbooksController extends Controller
{
	public $layout = 'admin_main';

	public function init()
    {
        parent::init();

    }

    public function actionIndex(){
	    if(Yii::$app->quickbooks->connect()){
	        return 'Connected to QuickBooks API';
        }
        else{
            return "Not connected to QuickBooks API. <a href='".Url::to(['quickbooks/start'])."'>Login with QuickBooks?</a>";

        }
	}

    public function actionStart(){
        $redirectURL = Yii::$app->quickbooks->redirectUrl();
        return $this->redirect($redirectURL);
    }

	public function actionOauth(){


        $request = Yii::$app->request;
        Yii::$app->quickbooks->handleOauthCallback();

        $redirectURL = Url::to(['quickbooks/index']);
        return $this->redirect($redirectURL);
    }
}