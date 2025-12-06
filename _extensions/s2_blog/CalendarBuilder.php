<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog;

use S2\Cms\Config\IntProxy;
use S2\Cms\Pdo\DbLayer;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CalendarBuilder
{
    public function __construct(
        private DbLayer             $dbLayer,
        private BlogUrlBuilder      $blogUrlBuilder,
        private TranslatorInterface $translator,
        private IntProxy            $startYear,
    ) {
    }

    /**
     * @param ?int $day 0 for skipping highlight, null for skipping header
     *
     * @throws \S2\Cms\Pdo\DbLayerException
     */
    public function calendar(?int $year = null, ?int $month = null, ?int $day = 0, string $url = '', array $dayUrls = null): string
    {
        if ($year === null) {
            $year = (int)date('Y');
        }

        if ($month === null) {
            $month = (int)date('m');
        }

        $startTime = mktime(0, 0, 0, $month, 1, $year);
        $endTime   = mktime(0, 0, 0, $month + 1, 1, $year);

        // Dealing with week days
        $currentColumnIndex = (int)date('w', $startTime);
        if ($this->translator->trans('Sunday starts week') !== '1') {
            --$currentColumnIndex;
            if ($currentColumnIndex === -1) {
                $currentColumnIndex = 6;
            }
        }

        // How many days have the month?
        $daysInThisMonth = (int)date('j', mktime(0, 0, 0, $month + 1, 0, $year)); // day = 0

        // Flags for the days when posts have been written
        if ($dayUrls === null) {
            $dayUrls = [];
            $result  = $this->dbLayer
                ->select('create_time, url')
                ->from('s2_blog_posts')
                ->where('create_time < :end_time')
                ->setParameter('end_time', $endTime)
                ->andWhere('create_time >= :start_time')
                ->setParameter('start_time', $startTime)
                ->andWhere('published = 1')
                ->execute()
            ;
            while ($row = $result->fetchRow()) {
                $dayUrls[1 + (int)(($row[0] - $startTime) / 86400)][] = $row[1];
            }
        }

        // Header
        $monthName = $this->month($month);
        if ($day === null) {
            // One of 12 year tables
            if ($startTime < time()) {
                $monthName = '<a href="' . $this->blogUrlBuilder->monthFromTimestamp($startTime) . '">' . $monthName . '</a>';
            }
            $header = '<tr class="nav"><th colspan="7">' . $monthName . '</th></tr>';
        } else {
            if ($day !== 0) {
                $monthName = '<a href="' . $this->blogUrlBuilder->monthFromTimestamp($startTime) . '">' . $monthName . '</a>';
            }

            // Links in the header
            $next_month = $endTime < time() ? '<a class="nav_mon" href="' . $this->blogUrlBuilder->monthFromTimestamp($endTime) . '" title="' . $this->month((int)date('m', $endTime)) . date(', Y', $endTime) . '">&rarr;</a>' : '&rarr;';

            $prevTime  = mktime(0, 0, 0, $month - 1, 1, $year);
            $prevMonth = $prevTime >= mktime(0, 0, 0, 1, 1, $this->startYear->get()) ? '<a class="nav_mon" href="' . $this->blogUrlBuilder->monthFromTimestamp($prevTime) . '" title="' . $this->month((int)date('m', $prevTime)) . date(', Y', $prevTime) . '">&larr;</a>' : '&larr;';

            $header = '<tr class="nav"><th>' . $prevMonth . '</th><th align="center" colspan="5">'
                . $monthName . ', <a href="' . $this->blogUrlBuilder->year($year) . '">' . $year . '</a></th><th>' . $next_month . '</th></tr>';
        }

        // Titles
        $output = '<table class="cal">' . $header . '<tr>';

        // Empty cells before
        for ($i = 0; $i < $currentColumnIndex; $i++) {
            $output .= '<td' . ($this->isWeekend($i) ? ' class="sun"' : '') . '></td>';
        }

        // Days
        for ($currentDayInMonth = 1; $currentDayInMonth <= $daysInThisMonth; $currentDayInMonth++) {
            $currentColumnIndex++;
            $cellContent = $currentDayInMonth; // Simple text content
            if (isset($dayUrls[$currentDayInMonth])) {
                if (\count($dayUrls[$currentDayInMonth]) !== 1 && ($currentDayInMonth !== $day || $url !== '')) {
                    // Several posts, link to the day page (if this is not the day selected)
                    $cellContent = '<a href="' . $this->blogUrlBuilder->day($year, $month, $currentDayInMonth) . '">' . $currentDayInMonth . '</a>';
                }
                if (\count($dayUrls[$currentDayInMonth]) === 1 && ($currentDayInMonth !== $day || $url === '')) {
                    // One post, link to it (if this is not the post selected)
                    $cellContent = '<a href="' . $this->blogUrlBuilder->post($year, $month, $currentDayInMonth, $dayUrls[$currentDayInMonth][0]) . '">' . $currentDayInMonth . '</a>';
                }
            }

            $classes = [];
            if ($currentDayInMonth === $day) {
                // Current day
                $classes[] = 'cur';
            }

            if ($this->isWeekend($currentColumnIndex)) {
                // Weekend
                $classes[] = 'sun';
            }

            $output .= '<td' . (!empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '') . '>'
                . $cellContent
                . '</td>';

            if (!($currentColumnIndex % 7) && ($currentDayInMonth !== $daysInThisMonth)) {
                $output .= '</tr><tr>';
            }
        }

        // Empty cells in the end
        while ($currentColumnIndex % 7) {
            $currentColumnIndex++;
            $output .= '<td' . ($this->isWeekend($currentColumnIndex) ? ' class="sun"' : '') . '></td>';
        }

        $output .= '</tr></table>';
        return $output;
    }

    public function month(int $month): string
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Month must be between 1 and 12');
        }
        $months = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');

        return $this->translator->trans($months[$month - 1]);
    }

    private function isWeekend(int $n): bool
    {
        if ($n % 7 === 0) {
            return true;
        }
        if ($n % 7 === 6 && $this->translator->trans('Sunday starts week') != '1') {
            return true;
        }
        if ($n % 7 === 1 && $this->translator->trans('Sunday starts week') == '1') {
            return true;
        }
        return false;
    }
}
