<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('role', 'administrator')->orderBy('id')->first();
        if (! $author) {
            $author = CambodianSchoolData::account('ADM001', 'administrator', ['name' => 'Sok Sovan', 'phone' => '011200999']);
        }
        $items = [
            ['Welcome Back to School', 'Welcome to a new term. Please confirm your class and contact details with the school office.', 'all'],
            ['Midterm Examination Schedule', 'Midterm assessments cover Mathematics, English, Khmer, Physics, Chemistry and Biology. Ask your subject teacher for your class schedule.', 'student'],
            ['Parent-Teacher Meeting', 'Guardians are invited to discuss learning progress and attendance with class teachers. Contact the office to book a meeting.', 'parent'],
            ['School Sports Day', 'Join our fictional school sports celebration. Bring drinking water, comfortable shoes and your team spirit.', 'all'],
            ['Final Examination Preparation', 'Revision sessions and practice papers are available from subject teachers. Build a balanced study plan.', 'student'],
            ['School Holiday Announcement', 'Please consult the school calendar for the upcoming holiday and confirm return dates with your class teacher.', 'all'],
            ['Library Reading Week', 'Explore Khmer and English stories during reading week. Return borrowed books to the library desk.', 'student'],
            ['Teacher Professional Development', 'Teachers will share lesson plans and classroom activities at the next professional development meeting.', 'teacher'],
            ['Transportation Registration', 'Parents may contact the office to discuss sample bus routes and transportation arrangements.', 'parent'],
            ['Community Science Exhibition', 'Families are invited to celebrate student projects in science and technology. Details are available from class teachers.', 'all'],
        ];
        foreach ($items as $index => [$title, $body, $audience]) {
            Announcement::firstOrCreate(['title' => $title], ['body' => $body, 'audience' => $audience,
                'published_at' => today()->subDays($index)->toDateString(), 'created_by' => $author->id]);
        }
    }
}
