<?php
/**
 * Part of the ETD Framework Renderer Package
 *
 * @copyright   Copyright (C) 2015 ETD Solutions, SARL Etudoo. Tous droits réservés.
 * @license     Apache License 2.0; see LICENSE
 * @author      ETD Solutions http://etd-solutions.com
 */

namespace EtdSolutions\Renderer;

use Joomla\Application\AbstractApplication;

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
     * Constructeur
     *
     * @param   AbstractApplication  $app  L'objet application.
     */
    public function __construct(AbstractApplication $app) {
        $this->app = $app;
    }

    /**
     * Retourne le nom de l'extension
     *
     * @return  string  Le nom de l'extension
     */
    public function getName() {

        return 'etd-interfaces';
    }

    /**
     * Retourne une liste des variables globales à ajouter à la liste existante.
     *
     * @return  array  Un tableau des variables globales.
     */
    public function getGlobals() {

        return [
            'uri' => $this->app->get('uri')
        ];
    }

    /**
     * Retourne une liste de fonctions à ajouter à la liste existante.
     *
     * @return  \Twig_SimpleFunction[]  Un tableau d'instances de \Twig_SimpleFunction.
     */
    public function getFunctions() {

        return [
            new \Twig_SimpleFunction('sprintf', 'sprintf'),
            new \Twig_SimpleFunction('stripJRoot', array($this, 'stripJRoot'))
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
            new \Twig_SimpleFilter('get_class', 'get_class'),
            new \Twig_SimpleFilter('json_decode', 'json_decode'),
            new \Twig_SimpleFilter('stripJRoot', array($this, 'stripJRoot'))
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
}
