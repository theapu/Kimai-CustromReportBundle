<?php

/*
 * This file is part of the Kimai CustomReportBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\CustomReportBundle\EventSubscriber;

use App\Event\ReportingEvent;
use App\Reporting\Report;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ReportingEventSubscriber implements EventSubscriberInterface
{
    private $security;

    public function __construct(AuthorizationCheckerInterface $security)
    {
        $this->security = $security;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ReportingEvent::class => ['onReportingMenu', 100],
        ];
    }

    public function onReportingMenu(ReportingEvent $event)
    {
        // perform your necessary permission checks
        if (!$this->security->isGranted('view_custom_report')) {
            return;
        }
        // add a report to the menu: unique id,      the route name,     the label to be translated
        $event->addReport(new Report('week_by_all', 'report_all_week', 'report_all_week'));
    }
}
