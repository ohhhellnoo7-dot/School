<section id="dashboard-khmer-calendar" class="khmer-calendar border-top p-3" aria-labelledby="khmer-calendar-title" lang="km">
    <div class="khmer-calendar-header">
        <div class="khmer-calendar-heading">
            <span class="khmer-calendar-icon"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
            <div>
                <h3 id="khmer-calendar-title">ប្រតិទិនខ្មែរ</h3>
                <p>ចន្ទគតិ និងថ្ងៃឈប់សម្រាក</p>
            </div>
        </div>
        <div class="khmer-calendar-controls" data-calendar-controls hidden>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-calendar-action="previous" aria-label="ខែមុន"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-calendar-action="today"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>ថ្ងៃនេះ</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-calendar-action="next" aria-label="ខែបន្ទាប់"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
        </div>
    </div>
    <div class="khmer-calendar-layout" data-calendar-layout hidden>
        <div class="khmer-calendar-month">
            <table class="khmer-calendar-grid">
                <caption data-calendar-month></caption>
                <thead><tr>
                    @foreach(['អាទិត្យ', 'ចន្ទ', 'អង្គារ', 'ពុធ', 'ព្រហស្បតិ៍', 'សុក្រ', 'សៅរ៍'] as $weekday)
                        <th scope="col">{{ $weekday }}</th>
                    @endforeach
                </tr></thead>
                <tbody></tbody>
            </table>
            <div class="khmer-calendar-legend">
                    <span><i class="bi bi-calendar-event" aria-hidden="true"></i> ថ្ងៃនេះ</span>
                    <span class="khmer-calendar-holiday-key"><i class="bi bi-flag-fill" aria-hidden="true"></i> ថ្ងៃឈប់សម្រាក</span>
            </div>
        </div>
        <div class="khmer-calendar-sidebar">
            <div class="khmer-calendar-selection" aria-live="polite" aria-atomic="true">
                    <h4><i class="bi bi-calendar-check" aria-hidden="true"></i> កាលបរិច្ឆេទដែលបានជ្រើសរើស</h4>
                    <p class="khmer-calendar-solar" data-calendar-solar></p>
                    <div class="khmer-calendar-detail">
                        <i class="bi bi-moon-stars" aria-hidden="true"></i>
                        <p data-calendar-date></p>
                    </div>
                    <div class="khmer-calendar-detail khmer-calendar-holiday-key" data-selected-holiday hidden>
                        <i class="bi bi-flag-fill" aria-hidden="true"></i>
                        <p></p>
                    </div>
            </div>
            <div class="khmer-calendar-holidays" data-calendar-holidays hidden>
                    <h4><i class="bi bi-flag" aria-hidden="true"></i> ថ្ងៃឈប់សម្រាកសាធារណៈ</h4>
                    <ul></ul>
                    <p class="mb-0" data-holiday-coverage hidden>មានទិន្នន័យថ្ងៃឈប់សម្រាកសម្រាប់ឆ្នាំ ២០២៦ និង ២០២៧។</p>
            </div>
        </div>
    </div>
    <p class="small mb-0" data-calendar-fallback lang="en">The Khmer calendar could not load.</p>
</section>
