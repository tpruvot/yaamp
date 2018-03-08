<?php
/**
 * CYiimpConsoleApp class file.
 *
 * Meant to replace run.php threads in a console/screen environment
 * allow to use common user()->getState/setState() and memcache
 *
 * php runconsole.php cronjob/runLoop2
 *     will execute actionRunLoop2() from <modules/thread/>CronjobController.php
 *
 * @author Tanguy Pruvot
 * @copyright 2017 YiiMP
 */

class CYiimpConsoleApp extends CConsoleApplication
{
	private $_controllerPath;
	private $_controller;

	public $defaultController = 'cronjob';

	public $layoutPath;
	public $layout;
	public $viewPath;
	public $systemViewPath;

	public $controllerMap=array();
	public $controllerNamespace;

	public $user;
	public $memcache;

	protected function init()
	{
		parent::init();
		$this->_controllerPath = $this->getBasePath().DIRECTORY_SEPARATOR.'modules/thread';

		$this->user = new CWebUser; // for user()->getState()
		$this->memcache = new YaampMemcache;
	}

	protected function registerCoreComponents()
	{
			parent::registerCoreComponents();
			$components = $this->getComponents();
			$components['user'] = array(
				'class'=>'CWebUser',
			);
			$this->setComponents($components);
	}

	public function createController($route,$owner=null)
	{
		if($owner===null)
			$owner=$this;
		if((array)$route===$route || ($route=trim($route,'/'))==='')
			$route=$owner->defaultController;
		$caseSensitive=$this->getUrlManager()->caseSensitive;

		$route.='/';
		while(($pos=strpos($route,'/'))!==false)
		{
			$id=substr($route,0,$pos);
			if(!preg_match('/^\w+$/',$id))
				return null;
			if(!$caseSensitive)
				$id=strtolower($id);
			$route=(string)substr($route,$pos+1);
			if(!isset($basePath))  // first segment
			{
				if(isset($owner->controllerMap[$id]))
				{
					return array(
						Yii::createComponent($owner->controllerMap[$id],$id,$owner===$this?null:$owner),
						$this->parseActionParams($route),
					);
				}

				if(($module=$owner->getModule($id))!==null)
					return $this->createController($route,$module);

				$basePath=$owner->getControllerPath();
				$controllerID='';
			}
			else
				$controllerID.='/';
			$className=ucfirst($id).'Controller';
			$classFile=$basePath.DIRECTORY_SEPARATOR.$className.'.php';

			if($owner->controllerNamespace!==null)
				$className=$owner->controllerNamespace.'\\'.str_replace('/','\\',$controllerID).$className;

			if(is_file($classFile))
			{
				if(!class_exists($className,false))
					require($classFile);
				if(class_exists($className,false) && is_subclass_of($className,'CController'))
				{
					$id[0]=strtolower($id[0]);
					return array(
						new $className($controllerID.$id,$owner===$this?null:$owner),
						$this->parseActionParams($route),
					);
				}
				return null;
			}
			$controllerID.=$id;
			$basePath.=DIRECTORY_SEPARATOR.$id;
		}
	}

	/**
	 * Creates the controller and performs the specified action.
	 * @param string $route the route of the current request. See {@link createController} for more details.
	 * @throws CException if the controller could not be created.
	 */
	public function runController($route)
	{
		if(($ca=$this->createController($route))!==null)
		{
			list($controller,$actionID)=$ca;
			$oldController=$this->_controller;
			$this->_controller=$controller;
			$controller->init();
			$controller->run($actionID);
			$this->_controller=$oldController;
		}
		else {
			throw new CException(Yii::t('yii', 'Unable to resolve the request "{route}". (CYiimpConsoleApp)',
				array('{route}'=>$route===''?$this->defaultController:$route)));
		}
	}

	// ---------------------------------------------------------------------------------------------------------------------

	/**
	 * Parses a path info into an action ID and GET variables.
	 * @param string $pathInfo path info
	 * @return string action ID
	 */
	protected function parseActionParams($pathInfo)
	{
		if(($pos=strpos($pathInfo,'/'))!==false)
		{
			$manager=$this->getUrlManager();
			$manager->parsePathInfo((string)substr($pathInfo,$pos+1));
			$actionID=substr($pathInfo,0,$pos);
			return $manager->caseSensitive ? $actionID : strtolower($actionID);
		}
		else
			return $pathInfo;
	}


	public function beforeControllerAction($controller,$action)
	{
		return true;
	}

	public function afterControllerAction($controller,$action)
	{
	}

	// ---------------------------------------------------------------------------------------------------------------------

	/**
	 * @return string the directory that contains the controller classes. Defaults to 'protected/controllers'.
	 */
	public function getControllerPath()
	{
		if($this->_controllerPath!==null)
			return $this->_controllerPath;
		else
			return $this->_controllerPath=$this->getBasePath().DIRECTORY_SEPARATOR.'controllers';
	}

	/**
	 * @param string $value the directory that contains the controller classes.
	 * @throws CException if the directory is invalid
	 */
	public function setControllerPath($value)
	{
		if(($this->_controllerPath=realpath($value))===false || !is_dir($this->_controllerPath))
			throw new CException(Yii::t('yii','The controller path "{path}" is not a valid directory.',
				array('{path}'=>$value)));
	}

}
