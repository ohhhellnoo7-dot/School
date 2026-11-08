(() => {
    const canvas = document.getElementById('teacherOverallChart');
    if (!canvas || typeof Chart === 'undefined') return;
    const values = JSON.parse(canvas.dataset.teacherValues);
    const total = values.reduce((sum, value) => sum + value, 0);
    new Chart(canvas, {
        type: 'doughnut',
        data: {labels: total ? ['Present', 'Absent', 'Late', 'Excused'] : ['No records'], datasets: [{data: total ? values : [1], backgroundColor: total ? ['#329c7c', '#d45669', '#c68b25', '#8d96af'] : ['#dce5e2'], borderWidth: 0}]},
        options: {maintainAspectRatio: false, cutout: '77%', plugins: {legend: {display: false}, tooltip: {enabled: total > 0}}}
    });
})();
