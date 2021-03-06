<?php

namespace Did\Routing;

use Exception;

/**
 * Class Catcher
 *
 * @package Did\Routing
 * @author (c) Julien Bernard <hello@julien-bernard.com>
 */
class Catcher
{
    /**
     * @param Exception $exception
     */
    public static function devCatch($exception)
    {
        echo "<p><b style='color: red; font-size:1.2em;'>Exception</b></p><p>";
        echo '<em>' . $exception->getmessage() . '</em>';
        echo("<br><br>File: " . $exception->getfile());
        echo("<br>Line: " . $exception->getline());
        echo '<pre>' . $exception->getTraceAsString() . '</pre>';
        echo "</p>";
    }
}