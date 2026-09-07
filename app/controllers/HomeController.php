<?php
/**
 * HomeController - landing page and error pages.
 *
 * Author : Tan Boon Leong (2402865)
 * Updated: Ong Kar Heng (2408830) - active-record dashboard counts
 * Module : Shared core - EcoCampus Waste Management System
 */
class HomeController extends Controller
{
    /** Dashboard: a quick health check that MVC, the ORM and the DB all work. */
    public function index(): void
    {
        $this->view('home/index', [
            'title'      => 'EcoCampus Waste Management System',
            'binCount'   => Bin::count('is_active', 1),
            'userCount'  => User::visibleCount(),
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
