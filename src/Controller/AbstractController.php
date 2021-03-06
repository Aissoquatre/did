<?php

namespace Did\Controller;

use Did\Kernel\Environment;
use Did\Routing\Params\Params;
use ReflectionClass;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

/**
 * Class AbstractController
 *
 * @package Did\Controller
 * @author (c) Julien Bernard <hello@julien-bernard.com>
 */
abstract class AbstractController
{
    /**
     * @var TwigEnvironment
     */
    private $twig;

    /**
     * @var ReflectionClass
     */
    private $reflectionClass;

    /**
     * @var array
     */
    private $templateParams;

    /**
     * @var string
     */
    protected $bundleName;

    /**
     * @var Params
     */
    protected $params;

    /**
     * @var array
     */
    protected $lang;

    const SUCCESS = 'success';
    const ERROR   = 'error';

    /**
     * AbstractController constructor.
     *
     * @param Params $params
     */
    public function __construct(Params $params)
    {
        $this->lang            = Environment::get()->findVar('LANG');
        $this->params          = $params;
        $this->reflectionClass = new ReflectionClass($this);
        $this->bundleName      = substr(
            strstr(
                substr($this->reflectionClass->getNamespaceName(), 0, -11),
                '\\'
            ),
            1
        );
        $this->twig            = new TwigEnvironment(
            new FilesystemLoader(Environment::get()->findVar('TWIG_TEMPLATES_DIR')), [
                'cache' => (Environment::get()->findVar('APP_ENV') === 'prod') ? Environment::get()->findVar('TWIG_CACHE') : false,
                'debug' => (Environment::get()->findVar('APP_ENV') === 'prod') ? false : true,
            ]
        );

        $this->twig->addExtension(new DebugExtension());
        $this->twig->addGlobal('_session', $_SESSION);
        $this->twig->addGlobal('_post', $_POST);
        $this->twig->addGlobal('_get', $_GET);

        // Set prevent cache global to be accessible in all templates
        $this->twig->addGlobal('_cache', '?' . Environment::get()->findVar('PREVENT_CACHE'));

        // Set filter which offers the possibility to translate a text code
        $this->addFilter([
            'name'     => 'translate',
            'callable' => function($string) {
                return isset($this->lang[$string]) ? $this->lang[$string] : $string;
            }
        ]);
    }

    /**
     * @param array $params
     */
    protected function setGlobal(array $params)
    {
        $this->twig->addGlobal($params['name'], $params['value']);
    }

    /**
     * @param array $params
     */
    protected function addFilter(array $params)
    {
        $this->twig->addFilter(new TwigFilter($params['name'], $params['callable']));
    }

    /**
     * @return array
     */
    public function server(): array
    {
        return $this->params->getServer();
    }

    /**
     * @return array
     */
    public function get(): array
    {
        return $this->params->getGet();
    }

    /**
     * @return array
     */
    public function post(): array
    {
        return $this->params->getPost();
    }

    /**
     * @param string $templateName
     * @param array $vars
     * @return AbstractController
     */
    public function _load(string $templateName, $vars = []): AbstractController
    {
        $this->templateParams = [
            'templateName' => $this->bundleName . '/View/' . $templateName . '.twig',
            'vars'         => $vars
        ];
        return $this;
    }

    /**
     * @return mixed
     */
    public function render()
    {
        return $this->twig->render($this->templateParams['templateName'], $this->templateParams['vars']);
    }

    /**
     * Display html in page
     */
    public function display()
    {
        echo $this->twig->render($this->templateParams['templateName'], $this->templateParams['vars']);
    }

    /**
     * @param string $status
     * @param mixed $datas
     */
    public function returnJson(string $status, $datas = null)
    {
        header('content-type: application/json');
        echo json_encode([
            'status' => $status,
            'data'   => $datas
        ]);
        exit;
    }
}