<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Paper;
use App\Models\Subject;
use App\Models\SubjectMember;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'total_classrooms' => Subject::count(),
                'total_users' => User::count(),
                'total_submissions' => Paper::count(),
                'pending_approvals' => SubjectMember::where('status', 'pending')->count(),
            ],
        ]);
    }
}

