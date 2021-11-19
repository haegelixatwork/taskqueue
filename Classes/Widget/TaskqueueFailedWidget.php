<?php
declare(strict_types=1);

namespace Undkonsorten\Taskqueue\Widget;

use FriendsOfTYPO3\Dashboard\Widgets\AbstractLineChartWidget;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;


class TaskqueueFailedWidget extends AbstractLineChartWidget
{
    /**
     * @var string
     */
    protected $title = 'Taskqueue failed fasks';

    /**
     * @var int
     */
    protected $width = 4;

    /**
     * @var int
     */
    protected $height = 4;

    public function prepareData(): void
    {
    }

    /**
     *
     */
    protected function prepareChartData(): void
    {
        parent::prepareChartData();

        $this->chartData = $this->getChartData();
    }

    /**
     * @return array
     */
    protected function getChartData(): array
    {
        $period = 'lastMonth';

        $labels = [];
        $data = [];

        if ($period === 'lastWeek') {
            for ($daysBefore=7; $daysBefore--; $daysBefore>0) {
                $labels[] = date('d-m-Y', strtotime('-' . $daysBefore . ' day'));
                $startPeriod = strtotime('-' . $daysBefore . ' day 0:00:00');
                $endPeriod =  strtotime('-' . $daysBefore . ' day 23:59:59');

                $data[] = $this->getNumberOfErrorsInPeriod($startPeriod, $endPeriod);
            }
        }

        if ($period === 'lastMonth') {
            for ($daysBefore=31; $daysBefore--; $daysBefore>0) {
                $labels[] = date('d-m-Y', strtotime('-' . $daysBefore . ' day'));
                $startPeriod = strtotime('-' . $daysBefore . ' day 0:00:00');
                $endPeriod =  strtotime('-' . $daysBefore . ' day 23:59:59');

                $data[] = $this->getNumberOfErrorsInPeriod($startPeriod, $endPeriod);
            }
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $this->getLanguageService()->sL($this->languagePrefix . 'widgets.sysLogErrors.chart.dataSet.0'),
                    'borderColor' => $this->chartColors[0],
                    'fill' => false,
                    'data' => $data
                ]
            ]
        ];
    }

    protected function getNumberOfErrorsInPeriod(int $start, int $end): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_taskqueue_domain_model_task');
        return (int)$queryBuilder
            ->select('*')
            ->from('tx_taskqueue_domain_model_task')
            ->where(
                $queryBuilder->expr()->eq('status', 3),
                $queryBuilder->expr()->gte('tstamp', $start),
                $queryBuilder->expr()->lte('tstamp', $end)
            )
            ->execute()
            ->rowCount();
    }
}
