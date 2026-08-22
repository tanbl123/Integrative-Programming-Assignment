<?php
/**
 * HomeController - landing page and error pages.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Shared core - EcoCampus Waste Management System
 */
class HomeController extends Controller
{
    /** Dashboard: a quick health check that MVC, the ORM and the DB all work. */
    public function index(): void
    {
        $this->view('home/index', [
            'title'      => 'EcoCampus Waste Management System',
            'binCount'   => Bin::count(),
            'userCount'  => User::count(),
            'fullBins'   => Bin::findFullBins(),
        ]);
    }

    /** Rendered whenever the router cannot match a URL. */
    public function notFound(): void
    {
        http_response_code(404);
        $this->view('home/404', ['title' => 'Page Not Found']);
    }
}
