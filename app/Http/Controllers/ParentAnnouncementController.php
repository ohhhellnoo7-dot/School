<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Support\SchoolModules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParentAnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $visible = SchoolModules::query('announcements', $request->user());
        $reads = DB::table('announcement_reads')
            ->where('user_id', $request->user()->id)
            ->whereColumn('announcement_id', 'announcements.id');

        $categories = ['' => 'All', ...Announcement::CATEGORIES, 'important' => 'Important'];
        $counts = (clone $visible)->selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category');
        $month = today()->startOfMonth();
        $summary = [
            'total' => $counts->sum(),
            'month' => (clone $visible)->where('published_at', '>=', $month)->where('published_at', '<', $month->copy()->addMonth())->count(),
            'important' => (clone $visible)->where('priority', 'important')->count(),
            'unread' => (clone $visible)->whereNotExists((clone $reads)->selectRaw('1'))->count(),
        ];
        $counts[''] = $summary['total'];
        $counts['important'] = $summary['important'];

        $category = $request->string('category')->toString();
        $audience = $request->string('audience')->toString();
        $search = trim($request->string('search')->limit(150)->toString());
        $sort = $request->string('sort')->toString() === 'oldest' ? 'oldest' : 'newest';
        $layout = $request->string('layout')->toString() === 'grid' ? 'grid' : 'list';

        if (! array_key_exists($category, $categories)) {
            $category = '';
        }
        if (! in_array($audience, ['all', 'parent'], true)) {
            $audience = '';
        }

        $direction = $sort === 'oldest' ? 'asc' : 'desc';
        $announcements = $visible->select('announcements.*')
            ->addSelect(['parent_read_at' => $reads->select('read_at')])
            ->when($category === 'important', fn ($query) => $query->where('priority', 'important'))
            ->when(isset(Announcement::CATEGORIES[$category]), fn ($query) => $query->where('category', $category))
            ->when($audience !== '', fn ($query) => $query->where('audience', $audience))
            ->when($search !== '', function ($query) use ($search) {
                $pattern = '%'.strtr(mb_strtolower($search), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
                $query->where(fn ($query) => $query->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(body) LIKE ? ESCAPE '!'", [$pattern]));
            })
            ->orderBy('published_at', $direction)
            ->orderBy('id', $direction)
            ->paginate(12)->withQueryString();

        return view('school.parent-announcements', compact('announcements', 'categories', 'counts', 'summary', 'category', 'audience', 'search', 'sort', 'layout'));
    }

    public function markRead(Request $request, Announcement $announcement): void
    {
        DB::table('announcement_reads')->insertOrIgnore([
            'announcement_id' => $announcement->id,
            'user_id' => $request->user()->id,
            'read_at' => now(),
        ]);
    }
}
