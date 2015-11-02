<?php
/**
 * Part of the ETD Framework Renderer Package
 *
 * @copyright   Copyright (C) 2015 ETD Solutions, SARL Etudoo. Tous droits réservés.
 * @license     Apache License 2.0; see LICENSE
 * @author      ETD Solutions http://etd-solutions.com
 */

namespace EtdSolutions\Renderer;

use EtdSolutions\Acl\Acl;
use EtdSolutions\User\User;
use EtdSolutions\Utility\DateUtility;
use EtdSolutions\Utility\LocaleUtility;
use EtdSolutions\Utility\RequireJSUtility;
use Joomla\Application\AbstractApplication;
use EtdSolutions\Language\LanguageFactory;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\Language\Text;
use Joomla\Utilities\ArrayHelper;

/**
 * Classe pour étendre Twig
 */
class TwigExtension extends \Twig_Extension {

    /**
     * L'objet application
     *
     * @var    AbstractApplication
     */
    private $app;

    /**
     * L'objet database
     *
     * @var    DatabaseDriver
     */
    private $db;

    /**
     * L'objet text
     *
     * @var    Text
     */
    private $text;

    /**
     * L'objet User
     *
     * @var    User
     */
    private $user;

    /**
     * Le container DI
     *
     * @var    Container
     */
    private $container;

    /**
     * L'objet RequireJSUtility
     *
     * @var RequireJSUtility
     */
    private $requirejs;

    /**
     * Constructeur
     *
     * @param AbstractApplication $app       L'objet application.
     * @param Container           $container Le container DI.
     */
    public function __construct(AbstractApplication $app, $container) {
        $this->app       = $app;
        $this->container = $container;
        $this->db        = $container->get('db');
        $this->text      = $container->get('language')->getText();
        $this->user      = $container->get('user')->load();
        $this->requirejs = new RequireJSUtility();
    }

    /**
     * Retourne le nom de l'extension
     *
     * @return  string  Le nom de l'extension
     */
    public function getName() {

        return 'etd-framework';
    }

    /**
     * Retourne une liste des variables globales à ajouter à la liste existante.
     *
     * @return  array  Un tableau des variables globales.
     */
    public function getGlobals() {

        $globals = [
            'uri'      => $this->app->get('uri'),
            'sitename' => $this->app->get('sitename'),
            'debug'    => $this->app->get('debug'),
            'config'   => $this->container->get('config'),
            'user'     => $this->user,
            'lang'     => (new LanguageFactory)->getLanguage()->get('iso')
        ];

        if ($this->app instanceof \EtdSolutions\Application\Web) {
            $globals['token']            = $this->app->getFormToken();
            $globals['activeController'] = $this->app->getActiveController();
            $globals['messages']         = $this->getMessages();
        }

        return $globals;
    }

    /**
     * Retourne une liste de fonctions à ajouter à la liste existante.
     *
     * @return  \Twig_SimpleFunction[]  Un tableau d'instances de \Twig_SimpleFunction.
     */
    public function getFunctions() {

        return [
            new \Twig_SimpleFunction('sprintf', 'sprintf'),
            new \Twig_SimpleFunction('var_dump', 'var_dump'),
            new \Twig_SimpleFunction('base64_encode', 'base64_encode'),
            new \Twig_SimpleFunction('stripJRoot', array($this, 'stripJRoot')),
            new \Twig_SimpleFunction('translate', array($this, 'translate')),
            new \Twig_SimpleFunction('stranslate', array($this, 'stranslate')),
            new \Twig_SimpleFunction('plural', array($this, 'plural')),
            new \Twig_SimpleFunction('script', array($this, 'script')),
            new \Twig_SimpleFunction('authorise', array($this, 'authorise')),
            new \Twig_SimpleFunction('authoriseGroup', array($this, 'authoriseGroup')),
            new \Twig_SimpleFunction('arrayToString', array($this, 'arrayToString')),
            new \Twig_SimpleFunction('addDomReadyJS', array($this, 'addDomReadyJS')),
            new \Twig_SimpleFunction('addRequirePackage', array($this, 'addRequirePackage')),
            new \Twig_SimpleFunction('addRequireJSModule', array($this, 'addRequireJSModule')),
            new \Twig_SimpleFunction('requireJS', array($this, 'requireJS')),
            new \Twig_SimpleFunction('printRequireJS', array($this, 'printRequireJS'), array('is_safe' => array('html'))),
            new \Twig_SimpleFunction('getUserAvatar', array($this, 'getUserAvatar'), array("is_safe" => array("html"))),
            new \Twig_SimpleFunction('getUserAvatarURI', array($this, 'getUserAvatarURI')),
            new \Twig_SimpleFunction('getUserProfileValue', array($this, 'getUserProfileValue')),
            new \Twig_SimpleFunction('localeconv', array($this, 'localeconv')),
            new \Twig_SimpleFunction('getUsed', function() {
                $lang = (new LanguageFactory)->getLanguage();
                return [
                    'used' => $lang->getUsed(),
                    'orphans' => $lang->getOrphans(),
                    'error' => $lang->getErrorFiles(),
                    'paths' => $lang->getPaths()
                ];
            })
        ];
    }

    /**
     * Retourne une liste de filtres à ajouter à la liste existante.
     *
     * @return  \Twig_SimpleFilter[]  Un tableau d'instances de \Twig_SimpleFilter.
     */
    public function getFilters() {

        return [
            new \Twig_SimpleFilter('basename', 'basename'),
            new \Twig_SimpleFilter('str_repeat', 'str_repeat'),
            new \Twig_SimpleFilter('get_class', 'get_class'),
            new \Twig_SimpleFilter('json_decode', 'json_decode'),
            new \Twig_SimpleFilter('var_dump', 'var_dump'),
            new \Twig_SimpleFilter('stripJRoot', array($this, 'stripJRoot')),
            new \Twig_SimpleFilter('translate', array($this, 'translate')),
            new \Twig_SimpleFilter('stranslate', array($this, 'stranslate')),
            new \Twig_SimpleFilter('plural', array($this, 'plural')),
            new \Twig_SimpleFilter('locale_date', array($this, 'locale_date')),
            new \Twig_SimpleFilter('money_format', array($this, 'money_format'))
        ];
    }

    /**
     * Remplace le chemin racine de l'application définit par la constante "JPATH_ROOT" par le string "APP_ROOT"
     *
     * @param   string  $string  Le string à changer
     *
     * @return  mixed
     */
    public function stripJRoot($string) {

        return str_replace(JPATH_ROOT, 'APP_ROOT', $string);
    }

    /**
     * Traduit le string dans la langue courrante.
     *
     * @param   string   $string                The string to translate.
     * @param   array    $parameters            Array of parameters for the string
     * @param   boolean  $jsSafe                True to escape the string for JavaScript output
     * @param   boolean  $interpretBackSlashes  To interpret backslashes (\\=\, \n=carriage return, \t=tabulation)
     *
     * @return  string  Le string traduit ou la clé si $script est true
     */
    public function translate($string, $parameters = array(), $jsSafe = false, $interpretBackSlashes = true) {

        return $this->text->translate($string, $parameters, $jsSafe, $interpretBackSlashes);
    }

    /**
     * Passes a string thru a sprintf.
     *
     * The last argument can take an array of options:
     *
     * array('jsSafe'=>boolean, 'interpretBackSlashes'=>boolean, 'script'=>boolean)
     *
     * where:
     *
     * jsSafe is a boolean to generate a javascript safe strings.
     * interpretBackSlashes is a boolean to interpret backslashes \\->\, \n->new line, \t->tabulation.
     * script is a boolean to indicate that the string will be push in the javascript language store.
     *
     * @param   string  $string  The format string.
     *
     * @return  string|null  The translated strings or the key if 'script' is true in the array of options.
     *
     * @note    This method can take a mixed number of arguments for the sprintf function
     */
    public function stranslate($string) {

        return call_user_func_array(array($this->text, 'sprintf'), func_get_args());

    }

    /**
     * Pluralises a string in the current language
     *
     * The last argument can take an array of options:
     *
     * array('jsSafe'=>boolean, 'interpretBackSlashes'=>boolean, 'script'=>boolean)
     *
     * where:
     *
     * jsSafe is a boolean to generate a javascript safe strings.
     * interpretBackSlashes is a boolean to interpret backslashes \\->\, \n->new line, \t->tabulation.
     * script is a boolean to indicate that the string will be push in the javascript language store.
     *
     * @param   string   $string  The format string.
     * @param   integer  $n       The number of items
     *
     * @return  string  The translated strings or the key if 'script' is true in the array of options
     *
     * @note    This method can take a mixed number of arguments for the sprintf function
     */
    public function plural($string, $n) {

        return call_user_func_array(array($this->text, 'plural'), func_get_args());

    }

    /**
     * Translate a string into the current language and stores it in the JavaScript language store.
     *
     * @param   string   $string                The string to translate.
     * @param   array    $parameters            Array of parameters for the string
     * @param   boolean  $jsSafe                True to escape the string for JavaScript output
     * @param   boolean  $interpretBackSlashes  To interpret backslashes (\\=\, \n=carriage return, \t=tabulation)
     *
     * @return  array
     */
    public function script($string = null, $parameters = [], $jsSafe = true, $interpretBackSlashes = true) {
        return $this->requirejs->script($string, $parameters, $jsSafe, $interpretBackSlashes);
    }

    /**
     * Méthode pour contrôler si l'utilisateur a le droit d'effectuer une action.
     *
     * @param   string $asset   La ressource à contrôler.
     * @param   int    $user_id L'identifiant de l'utilisateur demandeur.
     *
     * @return  boolean  True si autorisé, false sinon.
     */
    public function authorise($asset, $user_id = null) {

        return $this->user->load($user_id)->authorise($asset);

    }

    /**
     * Méthode pour contrôler si un groupe a le droit d'effectuer une action.
     *
     * @param   int    $group_id L'identifiant du gorupe demandeur.
     * @param   string $section  La section de l'application à contrôler.
     * @param   string $action   L'action de l'application à contrôler.
     *
     * @return  boolean  True si autorisé, false sinon.
     */
    public function authoriseGroup($group_id, $section, $action) {

        $acl = Acl::getInstance($this->db);

        return $acl->checkGroup($group_id, $section, $action);

    }

    /**
     * Méthode pour afficher une date au bon fuseau horaire.
     *
     * @param \Joomla\Date\Date|string $date   La date à formater.
     * @param string                   $format Le format.
     * @return string La date formatée.
     */
    public function locale_date($date, $format) {

        $date_utility = new DateUtility($this->app->get('timezone'));
        return $date_utility->format($date, $format);

    }

    /**
     * Méthode pour formater une chaine monétaire.
     *
     * @param string $number La chaine à formater.
     * @param string $format Le format.
     * @return string La chaine formatée.
     */
    public function money_format($number, $format = '%!i') {

        $utility = new LocaleUtility();
        return $utility->money_format($number, $format);

    }

    /**
     * @return array
     */
    public function localeconv() {

        return (new LocaleUtility())->localeconv();

    }

    /**
     * Ajoute du script JavaScript en ligne exécuté dans le contexte jQuery.
     * Il sera exécuté après que le DOM du document soit prêt.
     *
     * @param string $script  Le script JS à ajouter.
     * @param bool   $onTop   Place le script en haut de la pile.
     * @param string $modules Des modules additionnels à charger par RequireJS.
     *
     */
    public function addDomReadyJS($script, $onTop = false, $modules = '') {

        $module = "jquery";

        if (!empty($modules)) {
            $module .= ", " . $modules;
        }

        $module .= ", domReady!";

        $this->addRequireJSModule('domReady', 'js/vendor/domReady');
        $this->requireJS($module, $script, $onTop);

    }

    public function addRequireJSModule($module, $path, $shim = false, $deps = null, $exports = null, $init = null) {

        $this->requirejs->addRequireJSModule($module, $path, $shim, $deps, $exports, $init);

    }

    public function addRequirePackage($package) {

        $this->requirejs->addRequirePackage($package);

    }

    public function addRequireMap($prefix, $old, $new) {

        $this->requirejs->addRequireMap($prefix, $old, $new);

    }

    /**
     * Ajoute du JavaScript en ligne exécuté dans le contexte RequireJS.
     * Il sera exécuté après que le DOM du document soit prêt.
     *
     * @param string $module
     * @param string $script Le script JS à ajouter.
     * @param bool   $onTop  Place le script en haut de la pile.
     */
    public function requireJS($module, $script = '', $onTop = false) {

        $this->requirejs->requireJS($module, $script, $onTop);

    }

    /**
     * Effectue le rendu de la configuration RequireJS ainsi que des appels aux modules.
     *
     * @return string
     */
    public function printRequireJS() {

        return $this->requirejs->printRequireJS($this->app);

    }

    /**
     * Méthode pour convertir un array en une chaine.
     *
     * @param array  $array
     * @param string $inner_glue
     * @param string $outer_glue
     * @param bool   $keepOuterKey
     *
     * @return string
     */
    public function arrayToString(array $array, $inner_glue = '=', $outer_glue = ' ', $keepOuterKey = false) {
        return ArrayHelper::toString($array, $inner_glue, $outer_glue, $keepOuterKey);
    }

    /**
     * Renvoi le code HTML de l'avatar dans la bonne taille.
     *
     * @param int    $user_id    L'identifiant de l'utilisateur.
     * @param string $size       La taille de l'avatar.
     * @param array  $attributes Un tableau d'attributs à attache au code HTML.
     * @param bool   $link       Crée un lien vers la page du profil.
     *
     * @return string URI vers l'avatar.
     */
    public function getUserAvatar($user_id = null, $size = '40', $attributes = array(), $link = false) {

        $user = $this->user->load($user_id);

        $class = "avatar avatar-user avatar-" . $size;
        if (array_key_exists('class', $attributes)) {
            $class .= " " . $attributes['class'];
        }
        $attributes['class'] = $class;
        $attributes = ArrayHelper::toString($attributes);

        $html = '';

        if ($link) {
            $html = '<a href="' . $this->app->get('uri.base.path') . 'user/display/' . $user->username . '">';
        }

        $html .= '<img ' . $attributes . ' src="' . $this->getUserAvatarURI($user_id, $size) . '" alt="' . htmlspecialchars($user->name) . '">';

        if ($link) {
            $html .= '</a>';
        }

        return $html;

    }

    /**
     * Renvoi l'URI vers l'avatar dans la bonne taille.
     *
     * @param int    $user_id L'identifiant de l'utilisateur.
     * @param string $size    La taille de l'avatar.
     *
     * @return string URI vers l'avatar.
     */
    public function getUserAvatarURI($user_id = null, $size = '40') {

        $user = $this->user->load($user_id);

        if (!$user) {
            return false;
        }

        // On construit le chemin vers l'image.
        $image_path = property_exists($user->profile, 'avatarFile') ? "images/users/" . $user->profile->avatarFile . "_" . $size . ".jpg" : false;

        // On contrôle que l'avatar existe.
        if ($image_path === false || !file_exists(JPATH_MEDIA . "/" . $image_path)) {
            $image_path = "images/nobody_" . $size . ".jpg";
        }

        // On retourne la bonne URI.
        return $this->app->get('uri.media.path') . $image_path;

    }

    /**
     * Méthode pour récupérer la valeur d'une propriété dans le profil de l'utilisateur.
     *
     * @param string $property La propriété à récupérer
     * @param null   $user     L'objet utilisateur ou une clé primaire ou rien pour l'utilisateur courant
     *
     * @return mixed Null si la propriété n'existe pas, sa valeur sinon.
     */
    public function getUserProfileValue($property, $user = null) {
        // Si c'est une clé primaire, on récupère l'objet.
        if (is_numeric($user)) {
            $user = $this->container->get('user')->load($user);
        } elseif (!is_object($user)) { // Si ce n'est pas un objet, on prend l'utilisateur courant.
            $user = $this->container->get('user')->load();
        }
        // On teste la présence de la propriété
        if (property_exists($user, 'profile') && property_exists($user->profile, $property)) {
            return $user->profile->$property;
        }
        // On retourne null par défaut.
        return null;
    }

    /**
     * Méthode pour trier les messages de l'appli.
     *
     * @return array
     */
    protected function getMessages() {

        $messages = $this->app->getMessageQueue();
        $lists    = array();

        if (is_array($messages) && !empty($messages)) {
            foreach ($messages as $msg) {
                if (isset($msg['type']) && isset($msg['message'])) {
                    $lists[$msg['type']][] = $msg;
                }
            }
        }

        return $lists;

    }
}
