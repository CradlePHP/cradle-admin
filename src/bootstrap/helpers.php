<?php
/**
 * This file is part of a package designed for the CradlePHP Project.
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

use Cradle\Data\Registry;
use Cradle\Package\System\Helpers;

return function ($request, $response) {
    //add helpers
    $handlebars = $this->package('global')->handlebars();
    $cradle = $this;

    /**
     * Builds the report chart given the id or slug
     *
     * @param *string|int          report_id or report_slug
     *
     * @return string
     */
    $handlebars->registerHelper('report', function ($id) use ($cradle) {
        $payload = $cradle->makePayload();
        if (is_numeric($id)) {
            $payload['request']->setStage('report_id', $id);
        } else {
            $payload['request']->setStage('report_slug', $id);
        }

        $cradle->trigger('report-chart-prepare', $payload['request'], $payload['response']);
        $report = $payload['response']->getResults();

        $canvas = '<div class="report-chart-wrapper">
            <canvas data-do="report"';

        if (isset($report['chart_height']) && $report['chart_height']) {
            $canvas .= sprintf(' height="%s"', $report['chart_height']);
        }

        if (isset($report['chart_width']) && $report['chart_width']) {
            $canvas .= sprintf(' width="%s"', $report['chart_width']);
        }

        if (isset($report['fill']) && $report['fill']) {
            $canvas .= sprintf(' fill="%s"', $report['fill']);
        }

        if (isset($report['stepped']) && $report['stepped']) {
            $canvas .= sprintf(' stepped="%s"', $report['stepped']);
        }

        if (isset($report['options']['x_axis']) && $report['options']['x_axis']) {
            $canvas .= sprintf(" data-x_axis='%s'", json_encode($report['options']['x_axis']));
        }

        if (isset($report['options']['y_axis']) && $report['options']['y_axis']) {
            $canvas .= sprintf(" data-y_axis='%s'", json_encode($report['options']['y_axis']));
        }

        if (isset($report['circle']) && $report['circle']) {
            $canvas .= sprintf(" data-circle='%s'", $report['circle']);
        }

        $canvas .= sprintf(' data-chart="%s"', $report['chart_type']);

        $canvas .= sprintf(" data-label='%s'", $report['chart_label']);

        $canvas .= sprintf(" data-dataset='%s'", json_encode($report['dataset']));

        $canvas .= '></canvas></div>';

        return $canvas;
    });
};
