<?php

namespace Report\Service;

use Database\Mapper\Meeting as MeetingMapper;
use Database\Model\Member as MemberModel;
use Database\Model\SubDecision;
use Database\Model\Decision;
use Doctrine\ORM\EntityManager;
use Laminas\Mail\Transport\TransportInterface;
use Laminas\Mail\Message;
use Laminas\ProgressBar\Adapter\Console;
use Laminas\ProgressBar\ProgressBar;
use Report\Model\Meeting as ReportMeeting;
use Report\Model\Decision as ReportDecision;

class Meeting
{
    /** @var MeetingMapper $meetingMapper */
    private $meetingMapper;

    /** @var EntityManager $emReport */
    private $emReport;

    /** @var array $config */
    private $config;

    private $mailTransport;

    /**
     * @param MeetingMapper $meetingMapper
     * @param EntityManager $emReport
     * @param array $config
     * @param $mailTransport
     */
    public function __construct(
        MeetingMapper $meetingMapper,
        EntityManager $emReport,
        array $config,
        $mailTransport,
    ) {
        $this->meetingMapper = $meetingMapper;
        $this->emReport = $emReport;
        $this->config = $config;
        $this->mailTransport = $mailTransport;
    }

    /**
     * Export meetings.
     */
    public function generate()
    {
        // simply export every meeting
        $meetings = $this->meetingMapper->findAll(true, true);

        $adapter = new Console();
        $progress = new ProgressBar($adapter, 0, count($meetings));

        $num = 0;
        foreach ($meetings as $meeting) {
            $this->generateMeeting($meeting[0]);
            $this->emReport->flush();
            $this->emReport->clear();
            $progress->update(++$num);
        }

        $this->emReport->flush();
        $progress->finish();
    }

    public function generateMeeting($meeting)
    {
        $repo = $this->emReport->getRepository('Report\Model\Meeting');

        $reportMeeting = $repo->find([
            'type' => $meeting->getType(),
            'number' => $meeting->getNumber(),
        ]);

        if ($reportMeeting === null) {
            $reportMeeting = new ReportMeeting();
        }

        $reportMeeting->setType($meeting->getType());
        $reportMeeting->setNumber($meeting->getNumber());
        $reportMeeting->setDate($meeting->getDate());

        foreach ($meeting->getDecisions() as $decision) {
            try {
                $this->generateDecision($decision, $reportMeeting);
            } catch (\Exception $e) {
                // send email, something went wrong
                $this->sendDecisionExceptionMail($e, $decision);
                continue;
            }
        }

        $this->emReport->persist($reportMeeting);
    }

    public function generateDecision($decision, $reportMeeting = null)
    {
        $decRepo = $this->emReport->getRepository('Report\Model\Decision');

        if ($reportMeeting === null) {
            $reportMeeting = $this->emReport->getRepository('Report\Model\Meeting')->find([
                'type' => $decision->getMeeting()->getType(),
                'number' => $decision->getMeeting()->getNumber(),
            ]);

            if ($reportMeeting === null) {
                throw new \LogicException('Decision without meeting');
            }
        }

        // see if decision exists
        $reportDecision = $decRepo->find([
            'meeting_type' => $decision->getMeeting()->getType(),
            'meeting_number' => $decision->getMeeting()->getNumber(),
            'point' => $decision->getPoint(),
            'number' => $decision->getNumber(),
        ]);

        if (null === $reportDecision) {
            $reportDecision = new ReportDecision();
            $reportDecision->setMeeting($reportMeeting);
        }

        $reportDecision->setPoint($decision->getPoint());
        $reportDecision->setNumber($decision->getNumber());
        $content = [];

        foreach ($decision->getSubdecisions() as $subdecision) {
            $this->generateSubDecision($subdecision, $reportDecision);
            $content[] = $subdecision->getContent();
        }

        if (empty($content)) {
            $content[] = '';
        }

        $reportDecision->setContent(implode(' ', $content));

        $this->emReport->persist($reportDecision);
    }

    public function generateSubDecision($subdecision, $reportDecision = null)
    {
        $decRepo = $this->emReport->getRepository('Report\Model\Decision');
        $subdecRepo = $this->emReport->getRepository('Report\Model\SubDecision');

        if ($reportDecision === null) {
            $reportDecision = $decRepo->find([
                'meeting_type' => $subdecision->getMeetingType(),
                'meeting_number' => $subdecision->getMeetingNumber(),
                'point' => $subdecision->getDecisionPoint(),
                'number' => $subdecision->getDecisionNumber(),
            ]);

            if ($reportDecision === null) {
                throw new \LogicException('Decision without meeting');
            }
        }

        $reportSubDecision = $subdecRepo->find([
            'meeting_type' => $subdecision->getMeetingType(),
            'meeting_number' => $subdecision->getMeetingNumber(),
            'decision_point' => $subdecision->getDecisionPoint(),
            'decision_number' => $subdecision->getDecisionNumber(),
            'number' => $subdecision->getNumber(),
        ]);

        if (null === $reportSubDecision) {
            // determine type and create
            $class = get_class($subdecision);
            $class = preg_replace('/^Database/', 'Report', $class);
            $reportSubDecision = new $class();
            $reportSubDecision->setDecision($reportDecision);
            $reportSubDecision->setNumber($subdecision->getNumber());
        }

        if ($subdecision instanceof SubDecision\FoundationReference) {
            $ref = $subdecision->getFoundation();
            $foundation = $subdecRepo->find([
                'meeting_type' => $ref->getDecision()->getMeeting()->getType(),
                'meeting_number' => $ref->getDecision()->getMeeting()->getNumber(),
                'decision_point' => $ref->getDecision()->getPoint(),
                'decision_number' => $ref->getDecision()->getNumber(),
                'number' => $ref->getNumber(),
            ]);

            $reportSubDecision->setFoundation($foundation);
        }

        // transfer specific data
        if ($subdecision instanceof SubDecision\Installation) {
            // installation
            $reportSubDecision->setFunction($subdecision->getFunction());
            $reportSubDecision->setMember($this->findMember($subdecision->getMember()));
        } else {
            if ($subdecision instanceof SubDecision\Discharge) {
                // discharge
                $ref = $subdecision->getInstallation();
                $installation = $subdecRepo->find([
                    'meeting_type' => $ref->getDecision()->getMeeting()->getType(),
                    'meeting_number' => $ref->getDecision()->getMeeting()->getNumber(),
                    'decision_point' => $ref->getDecision()->getPoint(),
                    'decision_number' => $ref->getDecision()->getNumber(),
                    'number' => $ref->getNumber(),
                ]);
                $reportSubDecision->setInstallation($installation);
            } else {
                if ($subdecision instanceof SubDecision\Foundation) {
                    // foundation
                    $reportSubDecision->setAbbr($subdecision->getAbbr());
                    $reportSubDecision->setName($subdecision->getName());
                    $reportSubDecision->setOrganType($subdecision->getOrganType());
                } else {
                    if ($subdecision instanceof SubDecision\Reckoning || $subdecision instanceof SubDecision\Budget) {
                        // budget and reckoning
                        if (null !== $subdecision->getAuthor()) {
                            $reportSubDecision->setAuthor($this->findMember($subdecision->getAuthor()));
                        }
                        $reportSubDecision->setName($subdecision->getName());
                        $reportSubDecision->setVersion($subdecision->getVersion());
                        $reportSubDecision->setDate($subdecision->getDate());
                        $reportSubDecision->setApproval($subdecision->getApproval());
                        $reportSubDecision->setChanges($subdecision->getChanges());
                    } else {
                        if ($subdecision instanceof SubDecision\Board\Installation) {
                            // board installation
                            $reportSubDecision->setFunction($subdecision->getFunction());
                            $reportSubDecision->setMember($this->findMember($subdecision->getMember()));
                            $reportSubDecision->setDate($subdecision->getDate());
                        } else {
                            if ($subdecision instanceof SubDecision\Board\Release) {
                                // board release
                                $ref = $subdecision->getInstallation();
                                $installation = $subdecRepo->find([
                                    'meeting_type' => $ref->getDecision()->getMeeting()->getType(),
                                    'meeting_number' => $ref->getDecision()->getMeeting()->getNumber(),
                                    'decision_point' => $ref->getDecision()->getPoint(),
                                    'decision_number' => $ref->getDecision()->getNumber(),
                                    'number' => $ref->getNumber(),
                                ]);
                                $reportSubDecision->setInstallation($installation);
                                $reportSubDecision->setDate($subdecision->getDate());
                            } else {
                                if ($subdecision instanceof SubDecision\Board\Discharge) {
                                    $ref = $subdecision->getInstallation();
                                    $installation = $subdecRepo->find([
                                        'meeting_type' => $ref->getDecision()->getMeeting()->getType(),
                                        'meeting_number' => $ref->getDecision()->getMeeting()->getNumber(),
                                        'decision_point' => $ref->getDecision()->getPoint(),
                                        'decision_number' => $ref->getDecision()->getNumber(),
                                        'number' => $ref->getNumber(),
                                    ]);
                                    $reportSubDecision->setInstallation($installation);
                                } else {
                                    if ($subdecision instanceof SubDecision\Destroy) {
                                        $ref = $subdecision->getTarget();
                                        $target = $decRepo->find([
                                            'meeting_type' => $ref->getMeeting()->getType(),
                                            'meeting_number' => $ref->getMeeting()->getNumber(),
                                            'point' => $ref->getPoint(),
                                            'number' => $ref->getNumber(),
                                        ]);
                                        $reportSubDecision->setTarget($target);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Abolish decisions are handled by foundationreference
        // Other decisions don't need special handling

        // for any decision, make sure the content is filled
        $cnt = $subdecision->getContent();

        if (null === $cnt) {
            $cnt = '';
        }

        $reportSubDecision->setContent($cnt);
        $this->emReport->persist($reportSubDecision);

        return $reportSubDecision;
    }

    public function deleteDecision($decision)
    {
        $reportDecision = $this->emReport->getRepository('Report\Model\Decision')->find([
            'meeting_type' => $decision->getMeeting()->getType(),
            'meeting_number' => $decision->getMeeting()->getNumber(),
            'point' => $decision->getPoint(),
            'number' => $decision->getNumber(),
        ]);

        foreach (array_reverse($reportDecision->getSubdecisions()->toArray()) as $subDecision) {
            $this->deleteSubDecision($subDecision);
        }

        $this->emReport->remove($reportDecision);
    }

    public function deleteSubDecision($subDecision)
    {
        switch (true) {
            case $subDecision instanceof \Report\Model\SubDecision\Destroy:
                throw new \Exception('Deletion of destroy decisions not implemented');
                break;
            case $subDecision instanceof \Report\Model\SubDecision\Discharge:
                $installation = $subDecision->getInstallation();
                $installation->clearDischarge();
                $organMember = $subDecision->getInstallation()->getOrganMember();

                if ($organMember !== null) {
                    $organMember->setDischargeDate(null);
                }

                break;
            case $subDecision instanceof \Report\Model\SubDecision\Foundation:
                $organ = $subDecision->getOrgan();
                $this->emReport->remove($organ);
                break;
            case $subDecision instanceof \Report\Model\SubDecision\Installation:
                $organMember = $subDecision->getOrganMember();

                if ($organMember !== null) {
                    $this->emReport->remove($organMember);
                }

                break;
        }

        $this->emReport->remove($subDecision);
    }

    /**
     * Obtain the correct member, given a database member.
     *
     * @param MemberModel $member
     *
     * @return \Report\Model\Member
     */
    public function findMember(MemberModel $member)
    {
        $repo = $this->emReport->getRepository('Report\Model\Member');

        return $repo->find($member->getLidnr());
    }

    /**
     * Send an email about that something went wrong.
     *
     * @param Exception $e
     * @param Decision $decision
     */
    public function sendDecisionExceptionMail(\Exception $e, Decision $decision)
    {
        $config = $this->config['email'];

        $meeting = $decision->getMeeting();
        $body = <<<BODYTEXT
            Hallo Belangrijke Database Mensen,

            Ik ben een fout tegen gekomen tijdens het processen:

            {$e->getMessage()}

            Dit gebeurde tijdens het processen van besluit {$meeting->getType()->value} {$meeting->getNumber()}.{$decision->getNumber()}.{$decision->getPoint()}.

            Met vriendelijke groet,

            De GEWIS Database

            PS: extra info over de fout:

            {$e->getTraceAsString()}
            BODYTEXT;

        $message = new Message();
        $message->setBody($body);
        $message->setFrom($config['from']);
        $message->addTo($config['to']['report_error']);
        $message->setSubject('Database fout');

        $this->mailTransport->send($message);
    }
}
