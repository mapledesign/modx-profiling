<?php
/**
 * Tideways Profiling for MODX Revolution
 * Copyright (C) 2022-2023 Maple Design Ltd
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * This plugin needs to be enabled for the following System Events:
 * OnLoadWebDocument
 * OnWebPagePrerender
 * OnMODXInit
 * OnPageNotFound
 */

if (!class_exists('\Tideways\Profiler')) {
    return;
}

\Tideways\Profiler::watchCallback(
    'modScript::process',
    function($context) {
        $span = \Tideways\Profiler::createSpan(substr($context['object']->_class, 3));
        $span->annotate([
            'title' => $context['object']->get('name'),
            'tag' => $context['object']->_tag,
        ]);

        return $span;
    }
);
foreach (['modResponse', 'modX'] as $class) {
    \Tideways\Profiler::watchCallback(
        "$class::sendRedirect",
        function($context) {
            // Split as my eyes can't parse it on a single line
            $urlWithoutQs = explode('?', $_SERVER['REQUEST_URI'])[0];
            \Tideways\Profiler::setTransactionName(ltrim($urlWithoutQs, '/'));
        }
    );
}
/** @var modX $modx */
switch ($modx->event->name) {
    case 'OnMODXInit':
        /**
         * on MODX Init we get
         * @var string $contextKey
         */
        if ($contextKey === 'mgr') {
            \Tideways\Profiler::ignoreTransaction();
            return;
        }
        break;
    case 'OnPageNotFound':
        \Tideways\Profiler::ignoreTransaction();
        break;
    case 'OnLoadWebDocument':
        // TODO add a mapping file that people can use instead of this
        //$tName = ltrim($modx->makeUrl($modx->resource->id), '/');
        $tName = ltrim($_SERVER['REQUEST_URI'], '/');
        $tNameNew = null;
//        if (strpos($tName, 'whatson/display') === 0) {
//            $tNameNew = 'whatson/display';
//        }
//        if (!$tNameNew) {
//            if (strpos($tName, '.php') !== false) {
//                $tNameNew = 'a page ending .php';
//            }
//        }
        $modx->resource && \Tideways\Profiler::setTransactionName($tNameNew ? $tNameNew : $tName);
        break;
    case 'OnWebPagePrerender':
        if ($modx->user instanceof modUser) {
            if ($modx->user->hasSessionContext('mgr')) {
                $headers = $modx->response->contentType->get('headers');
                $headers[] = "Server-Timing: " . \Tideways\Profiler::generateServerTimingHeaderValue();
                $modx->response->contentType->set('headers', $headers);
            }
        }
        break;
}
