<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiChatEvaluation;
use Illuminate\Http\Request;

class ChatEvaluationController extends Controller
{
    public function index()
    {
        $reports = KpiChatEvaluation::orderByDesc('eval_date')->orderByDesc('id')->paginate(15);

        return view('kpi.chat-eval.index', compact('reports'));
    }

    public function create()
    {
        return view('kpi.chat-eval.create', ['questions' => KpiChatEvaluation::QUESTIONS]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'eval_date' => 'required|date',
            'coordinator_name' => 'required|string|max:100',
            'chats_reviewed' => 'required|integer|min:0',
            'answers' => 'required|array',
            'answers.*.answer' => 'required|in:Yes,No',
            'answers.*.score' => 'required|numeric|min:0',
        ]);

        $report = KpiChatEvaluation::create([
            'eval_date' => $validated['eval_date'],
            'coordinator_name' => $validated['coordinator_name'],
            'chats_reviewed' => $validated['chats_reviewed'],
            'answers' => $validated['answers'],
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('kpi.chat-eval.show', $report)->with('success', 'Chat evaluation saved.');
    }

    public function show(KpiChatEvaluation $report)
    {
        return view('kpi.chat-eval.show', compact('report'));
    }

    public function destroy(KpiChatEvaluation $report)
    {
        $report->delete();

        return redirect()->route('kpi.chat-eval.index')->with('success', 'Report deleted.');
    }
}
