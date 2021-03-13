<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\CustomReportBundle\Controller;

use App\Controller\AbstractController;
use App\Model\Statistic\Day;
use KimaiPlugin\CustomReportBundle\Reporting\WeeklyUserList;
use KimaiPlugin\CustomReportBundle\Reporting\WeeklyUserListForm;
use App\Repository\Query\UserQuery;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Controller used to render reports.
 *
 * @Route(path="/reporting")
 * @Security("is_granted('view_reporting')")
 */
final class CustomReportController extends AbstractController
{
    /**
     * @var TimesheetRepository
     */
    private $timesheetRepository;
    /**
     * @var UserRepository
     */
    private $userRepository;

    public function __construct(TimesheetRepository $timesheetRepository, UserRepository $userRepository)
    {
        $this->timesheetRepository = $timesheetRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route(path="/", name="reporting", methods={"GET"})
     *
     * @return Response
     */

    public function defaultReport(): Response
    {
        return $this->redirectToRoute('report_user_week');
    }


    private function canSelectUser(): bool
    {
        if (!$this->isGranted('view_other_timesheet')) {
            return false;
        }

        return true;
    }

    /**
     * @Route(path="/weekly_users_list", name="report_all_week", methods={"GET","POST"})
     * @Security("is_granted('view_other_timesheet')")
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function weeklyUsersList(Request $request): Response
    {
        $currentUser = $this->getUser();
        $dateTimeFactory = $this->getDateTimeFactory();
        $localeFormats = $this->getLocaleFormats($request->getLocale());

        $query = new UserQuery();
        $query->setCurrentUser($currentUser);
        $allUsers = $this->userRepository->getUsersForQuery($query);

        $rows = [];

        $values = new WeeklyUserList();
        $values->setDate($dateTimeFactory->getStartOfWeek());

        $form = $this->createForm(WeeklyUserListForm::class, $values, [
            'timezone' => $dateTimeFactory->getTimezone()->getName(),
            'start_date' => $values->getDate(),
            'format' => $localeFormats->getDateTypeFormat(),
        ]);

        $form->submit($request->query->all(), false);

        if ($form->isSubmitted() && !$form->isValid()) {
            $values->setDate($dateTimeFactory->getStartOfWeek());
        }

        if ($values->getDate() === null) {
            $values->setDate($dateTimeFactory->getStartOfWeek());
        }

        $start = $dateTimeFactory->getStartOfWeek($values->getDate());
        $end = $dateTimeFactory->getEndOfWeek($values->getDate());

        $previousWeek = clone $start;
        $previousWeek->modify('-1 week');

        $nextWeek = clone $start;
        $nextWeek->modify('+1 week');

        foreach ($allUsers as $user) {
            $rows[] = [
                'days' => $this->timesheetRepository->getDailyStats($user, $start, $end),
                'user' => $user
            ];
        }

        $days = [];

        if (isset($rows[0])) {
            /** @var Day $day */
            foreach ($rows[0]['days'] as $day) {
                $days[$day->getDay()->format('Ymd')] = $day->getDay();
            }
        }

        return $this->render('@CustomReport/weekly_user_list.html.twig', [
            'form' => $form->createView(),
            'rows' => $rows,
            'days' => $days,
            'current' => $start,
            'next' => $nextWeek,
            'previous' => $previousWeek,
        ]);
    }

    private function prepareMonthlyData(array $data): array
    {
        $days = [];

        foreach ($data as $day) {
            $days[$day->getDay()->format('Ymd')] = ['date' => $day->getDay(), 'duration' => 0];
        }

        $rows = [];

        /** @var Day $day */
        foreach ($data as $day) {
            $dayId = $day->getDay()->format('Ymd');
            foreach ($day->getDetails() as $id => $detail) {
                $projectId = $detail['project']->getId();
                if (!\array_key_exists($projectId, $rows)) {
                    $rows[$projectId] = [
                        'project' => $detail['project'],
                        'duration' => 0,
                        'days' => $days,
                        'activities' => [],
                    ];
                }

                $rows[$projectId]['duration'] += $detail['duration'];
                $rows[$projectId]['days'][$dayId]['duration'] += $detail['duration'];

                $activityId = $detail['activity']->getId();
                if (!\array_key_exists($activityId, $rows[$projectId]['activities'])) {
                    $rows[$projectId]['activities'][$activityId] = [
                        'activity' => $detail['activity'],
                        'duration' => 0,
                        'days' => $days,
                    ];
                }

                $rows[$projectId]['activities'][$activityId]['duration'] += $detail['duration'];
                $rows[$projectId]['activities'][$activityId]['days'][$dayId]['duration'] += $detail['duration'];
            }
        }

        return $rows;
    }
}
