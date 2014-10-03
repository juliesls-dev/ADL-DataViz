<?php

namespace VizHAAL\VisplatBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use VizHAAL\VisplatBundle\Graph\GraphChart;
use Symfony\Component\Validator\Constraints\DateTime;

class VisplatController extends Controller
{
    /**
     * Generate ADLs, Pie chart and table
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function statusAction(Request $request)
    {
        // Redirect admin to Admin page
        if ($this->get('security.context')->isGranted('ROLE_SUPERADMIN') && $this->get('security.context')->isGranted('ROLE_ADMIN') == false) {
            return $this->redirect($this->generateUrl('sonata_admin_dashboard'));
        }
        $patientId = $this->getDefaultPatient();
        $startDate = $this->getDefaultDate($patientId);
        // Create Status Graph, passing the first patient'id order by name
        $graphJSON = $this->createStatusGraph($patientId, $startDate, $startDate);
        return $this->render('VizHAALVisplatBundle:Graph:status.html.twig', array(
            'jsonDataPieChart' => $graphJSON['pieChart'], 'jsonStatusTable' => $graphJSON['statusTable'], 

        ));
    }

    /**
     * Verify Authentication
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function loginAction(Request $request)
    {
        $session = $request->getSession();

        // get the login error if there is one
        if ($request->attributes->has(SecurityContext::AUTHENTICATION_ERROR)) {
            $error = $request->attributes->get(SecurityContext::AUTHENTICATION_ERROR);
        } else {
            $error = $session->get(SecurityContext::AUTHENTICATION_ERROR);
            $session->remove(SecurityContext::AUTHENTICATION_ERROR);
        }

        return $this->render('VizHAALVisplatBundle:Login:login.html.twig', array(
            // last username entered by the user
            'last_username' => $session->get(SecurityContext::LAST_USERNAME),
            'error' => $error,
        ));
    }


    /**
     * Create a patient form to be embedded into a layout.html.twig
     *
     * @return \Symfony\Component\Form\Form
     */
    public function patientFormAction(Request $request)
    {
        $patientArray = array();
        // Get current user
        $doctor = $this->get('security.context')->getToken()->getUser();
        // Create a doctrine manager
        $em = $this->getDoctrine()->getManager();
        $patients = $em->getRepository('VizHAALVisplatBundle:User')->findPatientsOfDoctor($doctor->getId());
        if ($patients) {
            // Make an associative array
            foreach ($patients as $patient) {
                $patientArray[$patient['id']] = $patient['name'];
            }
        } else {
            throw new RuntimeException(
                'There is no patient in the database'
            );
        }
        $form = $this->createFormBuilder()
            ->add('patient', 'choice', array(
                'choices' => $patientArray,
                'required' => true,
                'label' => false,
            ))
            ->getForm();
        return $this->render('VizHAALVisplatBundle:Visplat:patientForm.html.twig', array(
            'form' => $form->createView()
        ));
    }


    /**
     * Create a date form to be embedded into a layout.html.twig
     *
     * @return \Symfony\Component\Form\Form
     */
    public function dateFormAction(Request $request)
    {
        $dateArray = array();
        // Get current user
        $user = $this->get('security.context')->getToken()->getUser();
        // Create a doctrine manager
        $em = $this->getDoctrine()->getManager();
        if ($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            // Get first patient
            $patient = $em->getRepository('VizHAALVisplatBundle:User')->findFirstPatientsOfDoctor($user->getId());
            $patientId = $patient['id'];
        } elseif ($this->get('security.context')->isGranted('ROLE_USER')) {
            $patientId = $user->getId();
        }
        $dates = $em->getRepository('VizHAALVisplatBundle:User')->findAllEventDate($patientId);
        if ($dates) {
            // Make an associative array
            foreach ($dates as $date) {
                $dateArray[$date['date']] = $date['date'];
            }
        } else {
            throw new RuntimeException(
                'There is no patient in the database'
            );
        }
//        $form = $this->createFormBuilder()
//            ->add('date', 'date', array(
//                'input' => 'string',
//                'widget' => 'single_text'
//            ))
//            ->getForm();
        $form = $this->createFormBuilder()
            ->add('startDate', 'choice', array(
                'choices' => $dateArray,
                'required' => true,
                'label' => false
            ))
            ->add('endDate', 'choice', array(
                'choices' => $dateArray,
                'required' => true,
                'label' => false,
//                'attr' => array('disabled' => 'disabled')
            ))
            ->getForm();
        return $this->render('VizHAALVisplatBundle:Visplat:dateForm.html.twig', array(
            'form' => $form->createView()
        ));
    }

    /**
     * Handle the Ajax request for updating the graph data.
     * @param Request $request
     * @return Response
     */
    public function handleAjaxUpdatePatientAction(Request $request)
    {
        // Get router object
        $router = $this->get('router');
        $currentUrl = $request->getUri();
        // Get current route
        // Get the JSON object from Ajax
        $patient = json_decode($request->getContent());
        // Verify whether the current user is Doctor or Patient
        if ($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            if ($patient->route == 'vizhaal_visplat_homepage') {
                $graphJSON = $this->createStatusGraph($patient->id, $patient->startDate, $patient->endDate);
            } elseif ($patient->route == 'vizhaal_visplat_dependency') {
                $graphJSON = $this->createDependencyGraph($patient->id, $patient->startDate, $patient->endDate);
            }
        } elseif ($this->get('security.context')->isGranted('ROLE_USER')) {
            // Get current user
            $user = $this->get('security.context')->getToken()->getUser();
            if ($patient->route == 'vizhaal_visplat_homepage') {
                $graphJSON = $this->createStatusGraph($user->getId(), $patient->startDate, $patient->endDate);
            } elseif ($patient->route == 'vizhaal_visplat_dependency') {
                $graphJSON = $this->createDependencyGraph($user->getId(), $patient->startDate, $patient->endDate);
            }
        }
        // Create the status graph
        return new Response(json_encode($graphJSON));

    }


    /**
     * Handle the Ajax request for updating the date field.
     * @param Request $request
     * @return Response
     */
    public function handleAjaxUpdateDateAction(Request $request)
    {
        // Get the JSON object from Ajax
        $patient = json_decode($request->getContent());
        $em = $this->getDoctrine()->getManager();
        if ($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $dates = $em->getRepository('VizHAALVisplatBundle:User')->findAllEventDate($patient->id);
        } elseif ($this->get('security.context')->isGranted('ROLE_USER')) {
            // Get current user
            $user = $this->get('security.context')->getToken()->getUser();
            $dates = $em->getRepository('VizHAALVisplatBundle:User')->findAllEventDate($user->getId());
        }
        if ($dates) {
            // Make an associative array
            foreach ($dates as $date) {
                $dateArray[] = $date['date'];
            }
        } else {
            throw new RuntimeException(
                'There is no patient in the database'
            );
        }
        // Create the status graph
        return new Response(json_encode($dateArray));

    }

    /**
     * Create all the status graphs
     *
     * @param $patientId
     * @return array of JSON data
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function createStatusGraph($patientId, $startDate, $endDate)
    {
        // Create a doctrine manager
        $em = $this->getDoctrine()->getManager();
        $pieEvents = $em->getRepository('VizHAALVisplatBundle:User')->findAllGroupByEvent($patientId, $startDate, $endDate);
		$jsonStatusTable = $em->getRepository('VizHAALVisplatBundle:User')->findLastTime($patientId, $startDate, $endDate);
        if (!$pieEvents) {
            throw $this->createNotFoundException('Unable to find events for the given date, Are you sure that your dataset is correct?');
        }
		if (!$jsonStatusTable) {
            throw $this->createNotFoundException('Unable to find events for the given date, Are you sure that your dataset is correct?');
        }

        $startDateFormat = new \DateTime(date('Y-m-d', strtotime(str_replace('/', '-', $startDate))));
        $endDateFormat = new \DateTime(date('Y-m-d', strtotime(str_replace('/', '-', $endDate))));
        $diff = $startDateFormat->diff($endDateFormat);
        $jsonDataPieChart = GraphChart::createPieChart($pieEvents, $diff->days + 1);
		$jsonStatusTable = GraphChart::createStatusTable($jsonStatusTable);
        return array('pieChart' => $jsonDataPieChart, 'statusTable'=>$jsonStatusTable);
    }

    /**
     * Generate Chord Diagram
     * @param Request $request
     * @return Response
     */
    public function dependencyAction(Request $request)
    {
        // Redirect admin to Admin page
        if ($this->get('security.context')->isGranted('ROLE_SUPERADMIN') && $this->get('security.context')->isGranted('ROLE_ADMIN') == false) {
            return $this->redirect($this->generateUrl('sonata_admin_dashboard'));
        }
        $patientId = $this->getDefaultPatient();
        $startDate = $this->getDefaultDate($patientId);
        $eventMatrix = $this->createDependencyGraph($patientId, $startDate, $startDate);
        return $this->render('VizHAALVisplatBundle:Graph:dependency.html.twig', array(
            'events' => $eventMatrix['events'],
            'matrix' => $eventMatrix['matrix'],
            'jsonDataGanttChart' => $eventMatrix['ganttChart']
        ));
    }

    public function createDependencyGraph($patientId, $startDate, $endDate)
    {
        // Create a doctrine manager
        $em = $this->getDoctrine()->getManager();
        $allEvents = $em->getRepository('VizHAALVisplatBundle:User')->findAllEvents($patientId, $startDate, $endDate);
        $distinctEvents = $em->getRepository('VizHAALVisplatBundle:User')->findDistinctEvents($patientId, $startDate, $endDate);
        $eventsMatrix = GraphChart::createChordDiagram($allEvents, $distinctEvents);
        $ganttEvents = $em->getRepository('VizHAALVisplatBundle:User')->findAllEvents($patientId, $startDate, $endDate);
        if (!$ganttEvents) {
            throw $this->createNotFoundException('Unable to find events for the given date, Are you sure that your dataset is correct?');
        }
        $jsonDataGanttChart = GraphChart::createGanttChart($ganttEvents);
        return array('events' => $eventsMatrix['events'], 'matrix' => $eventsMatrix['matrix'], 'ganttChart' => $jsonDataGanttChart);

    }

    public function getDefaultPatient()
    {
        $em = $this->getDoctrine()->getManager();
        // Get current user
        $user = $this->get('security.context')->getToken()->getUser();
        $em = $this->getDoctrine()->getManager();
        // Verify whether the current is a doctor or a patient
        if ($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            // Get first patient
            $patient = $em->getRepository('VizHAALVisplatBundle:User')->findFirstPatientsOfDoctor($user->getId());
            $patientId = $patient['id'];
        } elseif ($this->get('security.context')->isGranted('ROLE_USER')) {
            $patientId = $user->getId();
        }
        if ($patientId == null) {
            throw new RuntimeException("Unable to find a patient, you must add a patient to the current doctor by using Dashboard");
        }
        return $patientId;
    }

    public function getDefaultDate($patientId)
    {
        $em = $this->getDoctrine()->getManager();
        // Get first date of the patient
        $startDate = $em->getRepository('VizHAALVisplatBundle:User')->findFirstEventDate($patientId);
        return $startDate;
    }
}
