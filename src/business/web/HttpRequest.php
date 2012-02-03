<?php
namespace Blocks;

/**
 *
 */
class HttpRequest extends \CHttpRequest
{
	public $actionHandle;
	public $actionController;
	public $actionAction;

	private $_urlFormat;
	private $_path;
	private $_queryStringPath;
	private $_pathSegments;
	private $_pathExtension;
	private $_mode;
	private $_isMobileBrowser;

	public function init()
	{
		parent::init();
		Blocks::app()->attachEventHandler('onBeginRequest',array($this,'correctUrlFormat'));
	}

	/**
	 * If there is no URL path, check for a path against the "other" URL format, and redirect to the correct URL format if we find one
	 */
	public function correctUrlFormat()
	{
		if (!$this->path)
		{
			if ($this->urlFormat == UrlFormat::PathInfo)
			{
				if ($this->queryStringPath)
				{
					$params = isset($_GET) ? $_GET : array();
					$pathVar = Blocks::app()->config->getItem('pathVar');
					unset($params[$pathVar]);
					$url = UrlHelper::generateUrl($this->queryStringPath, $params);
					$this->redirect($url);
				}
			}
			else
			{
				if ($this->pathInfo)
				{
					$params = isset($_GET) ? $_GET : array();
					$url = UrlHelper::generateUrl($this->pathInfo, $params);
					$this->redirect($url);
				}
			}
		}
	}

	public function getPath()
	{
		if (!isset($this->_path))
		{
			if ($this->urlFormat == UrlFormat::PathInfo)
				$this->_path = $this->pathInfo;
			else
				$this->_path = $this->queryStringPath;
		}

		return $this->_path;
	}

	public function getQueryStringPath()
	{
		if (!isset($this->_queryStringPath))
		{
			$pathVar = Blocks::app()->config->getItem('pathVar');
			$this->_queryStringPath = trim($this->getQuery($pathVar, ''), '/');
		}

		return $this->_queryStringPath;
	}

	/**
	 * @return mixed
	 */
	public function getPathSegments()
	{
		if (!isset($this->_pathSegments))
		{
			$this->_pathSegments = array_filter(explode('/', $this->path));
		}

		return $this->_pathSegments;
	}

	/**
	 * Returns a specific path segment
	 * @param      $num
	 * @param null $default
	 * @return mixed The requested path segment, or null
	 */
	public function getPathSegment($num, $default = null)
	{
		if (isset($this->pathSegments[$num-1]))
			return $this->pathSegments[$num-1];

		return $default;
	}

	/**
	 * @return mixed
	 */
	public function getPathExtension()
	{
		if (!isset($this->_pathExtension))
		{
			$ext = pathinfo($this->path, PATHINFO_EXTENSION);
			$this->_pathExtension = strtolower($ext);
		}

		return $this->_pathExtension;
	}

	/**
	 * 
	 * @return Returns which URL format we're using (PATH_INFO or the query string)
	 */
	public function getUrlFormat()
	{
		if (!isset($this->_urlFormat))
		{
			// If config[urlFormat] is set to either PathInfo or QueryString, take their word for it.
			if (Blocks::app()->config->getItem('urlFormat') == UrlFormat::PathInfo)
			{
				$this->_urlFormat = UrlFormat::PathInfo;
			}
			else if (Blocks::app()->config->getItem('urlFormat') == UrlFormat::QueryString)
			{
				$this->_urlFormat = UrlFormat::QueryString;
			}
			// Check if it's cached
			else if (($cachedUrlFormat = Blocks::app()->fileCache->get('urlFormat')) !== false)
			{
				$this->_urlFormat = $cachedUrlFormat;
			}
			else
			{
				// If there is already a PATH_INFO var available, we know it supports it.
				if (isset($_SERVER['PATH_INFO']))
				{
					$this->_urlFormat = UrlFormat::PathInfo;
				}
				// If there is already a routeVar=value in the current request URL, we're going to assume it's a QueryString request
				else if ($this->getQuery(Blocks::app()->config->getItem('pathVar')) !== null)
				{
					$this->_urlFormat = UrlFormat::QueryString;
				}
				else
				{
					$this->_urlFormat = UrlFormat::QueryString;

					// Last ditch, let's try to determine if PATH_INFO is enabled on the server.
					try
					{
						$context = stream_context_create(array('http' => array('header' => 'Connection: close')));
						$url = Blocks::app()->request->hostInfo.Blocks::app()->request->url.'/testpathinfo';
						if (($result = @file_get_contents($url, 0, $context)) !== false)
						{
							if ($result === 'success')
							{
								$this->_urlFormat = UrlFormat::PathInfo;
							}
						}
					}
					catch (Exception $e)
					{
						Blocks::log('Unable to determine if server PATH_INFO is enabled: '.$e->getMessage());
					}
				}

				// cache it and set it to expire according to config
				Blocks::app()->fileCache->set('urlFormat', $this->_urlFormat, Blocks::app()->config->getItem('cacheTimeSeconds'));
			}
		}

		return $this->_urlFormat;
	}

	/**
	 * @return string The app mode (Action, Resource, CP, or Site)
	 */
	public function getMode()
	{
		if (!isset($this->_mode))
		{
			$resourceTriggerWord = Blocks::app()->config->getItem('resourceTriggerWord');
			$actionTriggerWord = Blocks::app()->config->getItem('actionTriggerWord');

			if ($this->getPathSegment(1) === $resourceTriggerWord)
				$this->_mode = RequestMode::Resource;

			else if ($this->getPathSegment(1) === $actionTriggerWord)
			{
				$this->_mode = RequestMode::Action;
				$this->actionHandle     = $this->getPathSegment(2);
				$this->actionController = $this->getPathSegment(3, 'default');
				$this->actionAction     = $this->getPathSegment(4, 'index');
			}

			else if (($action = $this->getPost($actionTriggerWord)) !== null && ($actionParts = array_filter(explode('/', $action))))
			{
				$this->_mode = RequestMode::Action;
				$this->actionHandle     = isset($actionParts[0]) ? $actionParts[0] : null;
				$this->actionController = isset($actionParts[1]) ? $actionParts[1] : 'default';
				$this->actionAction     = isset($actionParts[2]) ? $actionParts[2] : 'index';
			}

			else if (BLOCKS_CP_REQUEST === true)
				$this->_mode = RequestMode::CP;

			else
				$this->_mode = RequestMode::Site;
		}

		return $this->_mode;
	}

	/**
	 * Returns whether the request is coming from a mobile browser
	 * Regex courtesy of http://www.dannyherran.com/2011/02/detect-mobile-browseruser-agent-with-php-ipad-iphone-blackberry-and-others/
	 * @return bool Whether the request is coming from a mobile browser
	 */
	public function getIsMobileBrowser()
	{
		if (!isset($this->_isMobileBrowser))
		{
			if (preg_match('/(alcatel|amoi|android|avantgo|blackberry|benq|cell|cricket|docomo|elaine|htc|iemobile|iphone|ipad|ipaq|ipod|j2me|java|midp|mini|mmp|mobi|motorola|nec-|nokia|palm|panasonic|philips|phone|sagem|sharp|sie-|smartphone|sony|symbian|t-mobile|telus|up\.browser|up\.link|vodafone|wap|webos|wireless|xda|xoom|zte)/i', $_SERVER['HTTP_USER_AGENT']))
				$this->_isMobileBrowser = true;
			else
				$this->_isMobileBrowser = false;
		}

		return $this->_isMobileBrowser;
	}
}
