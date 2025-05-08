/**
 * Chart.js Configuration for statistics
 */

/**
 * Initialize student progress chart
 * @param {string} canvasId - Canvas element ID
 * @param {Array} labels - Chart labels (e.g., month names)
 * @param {Array} data - Chart data values
 */
function initStudentProgressChart(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Nilai Rata-rata',
                data: data,
                backgroundColor: 'rgba(52, 152, 219, 0.2)',
                borderColor: 'rgba(52, 152, 219, 1)',
                borderWidth: 2,
                tension: 0.3,
                pointBackgroundColor: '#fff',
                pointBorderColor: 'rgba(52, 152, 219, 1)',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(44, 62, 80, 0.9)',
                    titleColor: '#ecf0f1',
                    bodyColor: '#ecf0f1',
                    borderColor: 'rgba(52, 152, 219, 0.5)',
                    borderWidth: 1
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        stepSize: 10
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                }
            }
        }
    });
}

/**
 * Initialize pie chart for quiz results distribution
 * @param {string} canvasId - Canvas element ID
 * @param {Array} labels - Chart labels
 * @param {Array} data - Chart data values
 */
function initQuizDistributionChart(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: [
                    'rgba(46, 204, 113, 0.8)',
                    'rgba(52, 152, 219, 0.8)',
                    'rgba(241, 196, 15, 0.8)',
                    'rgba(231, 76, 60, 0.8)',
                ],
                borderColor: [
                    'rgba(46, 204, 113, 1)',
                    'rgba(52, 152, 219, 1)',
                    'rgba(241, 196, 15, 1)',
                    'rgba(231, 76, 60, 1)',
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    backgroundColor: 'rgba(44, 62, 80, 0.9)',
                    titleColor: '#ecf0f1',
                    bodyColor: '#ecf0f1',
                }
            },
            cutout: '70%'
        }
    });
}

/**
 * Initialize bar chart for student activity
 * @param {string} canvasId - Canvas element ID
 * @param {Array} labels - Chart labels
 * @param {Array} data - Chart data values
 */
function initActivityChart(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Aktivitas',
                data: data,
                backgroundColor: 'rgba(52, 152, 219, 0.7)',
                borderColor: 'rgba(52, 152, 219, 1)',
                borderWidth: 1,
                borderRadius: 4,
                barThickness: 20,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    backgroundColor: 'rgba(44, 62, 80, 0.9)',
                    titleColor: '#ecf0f1',
                    bodyColor: '#ecf0f1',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

/**
 * Initialize radar chart for student skills
 * @param {string} canvasId - Canvas element ID
 * @param {Array} labels - Chart labels
 * @param {Array} data - Chart data values
 */
function initSkillsChart(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    return new Chart(ctx, {
        type: 'radar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Kemampuan',
                data: data,
                backgroundColor: 'rgba(46, 204, 113, 0.2)',
                borderColor: 'rgba(46, 204, 113, 1)',
                borderWidth: 2,
                pointBackgroundColor: 'rgba(46, 204, 113, 1)',
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    backgroundColor: 'rgba(44, 62, 80, 0.9)',
                    titleColor: '#ecf0f1',
                    bodyColor: '#ecf0f1',
                }
            },
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        stepSize: 20,
                        display: false
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    angleLines: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    pointLabels: {
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });
}

/**
 * Initialize comparison chart for progress across different categories
 * @param {string} canvasId - Canvas element ID
 * @param {Array} labels - Chart labels (categories)
 * @param {Array} datasets - Multiple datasets for comparison
 */
function initComparisonChart(canvasId, labels, datasets) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    // Process datasets to add styling
    const chartDatasets = datasets.map((dataset, index) => {
        const colors = [
            { bg: 'rgba(52, 152, 219, 0.2)', border: 'rgba(52, 152, 219, 1)' },
            { bg: 'rgba(46, 204, 113, 0.2)', border: 'rgba(46, 204, 113, 1)' },
            { bg: 'rgba(155, 89, 182, 0.2)', border: 'rgba(155, 89, 182, 1)' },
            { bg: 'rgba(241, 196, 15, 0.2)', border: 'rgba(241, 196, 15, 1)' }
        ];
        
        const colorIndex = index % colors.length;
        
        return {
            label: dataset.label,
            data: dataset.data,
            backgroundColor: colors[colorIndex].bg,
            borderColor: colors[colorIndex].border,
            borderWidth: 2
        };
    });
    
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: chartDatasets
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(44, 62, 80, 0.9)',
                    titleColor: '#ecf0f1',
                    bodyColor: '#ecf0f1',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        stepSize: 10
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}
