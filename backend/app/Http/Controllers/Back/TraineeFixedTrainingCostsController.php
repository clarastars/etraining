<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\Back\Trainee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TraineeFixedTrainingCostsController extends Controller
{
    public function index($id)
    {
        $this->authorize('override-training-costs');

        return Inertia::render('Back/Trainees/FixedTrainingCosts/Index', [
            'trainee' => Trainee::findOrFail($id),
        ]);
    }

    public function update($id, Request $request)
    {
        $this->authorize('override-training-costs');

        $request->validate([
            'override_training_costs' => 'nullable|numeric',
            'platform_reward' => 'nullable|numeric',
            'ignore_attendance' => 'nullable|boolean',
            'dont_edit_notice' => 'nullable|boolean',
        ]);

        $trainee = Trainee::findOrFail($id);
        $trainee->override_training_costs = $request->override_training_costs;
        $trainee->platform_reward = $request->platform_reward;
        $trainee->ignore_attendance = $request->boolean('ignore_attendance');
        $trainee->dont_edit_notice = $request->boolean('dont_edit_notice');
        $trainee->save();

        return redirect()->to($trainee->show_url);
    }
}
