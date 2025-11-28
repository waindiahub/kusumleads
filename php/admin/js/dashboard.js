// Dashboard JavaScript
let statusChart, pnlChart, campaignChart;

document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    // Auto-refresh every 30 seconds
    setInterval(loadDashboardData, 30000);
});

async function loadDashboardData() {
    try {
        // Load dashboard stats
        const response = await fetch('../includes/web_reports.php?endpoint=dashboard');
        const data = await response.json();
        
        if (data.success) {
            updateStatsCards(data.data);
        }
        
        // Load enhanced stats
        const enhancedResponse = await fetch('../includes/web_reports.php?endpoint=enhanced-stats');
        const enhancedData = await enhancedResponse.json();
        
        if (enhancedData.success) {
            updateEnhancedStats(enhancedData.data);
        }
        
        // Load activity feed
        loadActivityFeed();
        
        // Load charts
        loadCharts();
        
        // Load top performers
        loadTopPerformers();
        
        // Load campaign performance
        loadCampaignPerformance();
    } catch (error) {
        console.error('Error loading dashboard data:', error);
    }
}

function updateStatsCards(stats) {
    document.getElementById('totalLeads').textContent = stats.total_leads || 0;
    document.getElementById('activeAgents').textContent = stats.active_agents || 0;
    document.getElementById('todayLeads').textContent = stats.today_leads || 0;
    
    // Calculate qualified leads from status breakdown
    const qualified = stats.leads_by_status?.find(s => s.status === 'qualified')?.count || 0;
    document.getElementById('qualifiedLeads').textContent = qualified;
}

function updateEnhancedStats(stats) {
    // Update revenue
    const revenueEl = document.getElementById('totalRevenue');
    if (revenueEl) {
        revenueEl.textContent = '₹' + (stats.total_revenue || 0).toLocaleString('en-IN');
    }
    
    // Update today's revenue
    const todayRevenueEl = document.getElementById('todayRevenue');
    if (todayRevenueEl) {
        todayRevenueEl.textContent = '₹' + (stats.today_revenue || 0).toLocaleString('en-IN') + ' today';
    }
    
    // Update pending leads
    const pendingEl = document.getElementById('pendingLeads');
    if (pendingEl) {
        pendingEl.textContent = stats.pending_leads || 0;
    }
    
    // Update conversion rate
    const convRateEl = document.getElementById('conversionRate');
    if (convRateEl) {
        convRateEl.textContent = (stats.conversion_rate || 0) + '%';
    }
    
    // Update online agents
    const onlineAgentsEl = document.getElementById('onlineAgents');
    if (onlineAgentsEl) {
        onlineAgentsEl.textContent = stats.online_agents || 0 + ' online';
    }
    
    // Update average response time
    const avgResponseEl = document.getElementById('avgResponseTime');
    if (avgResponseEl) {
        avgResponseEl.textContent = (stats.avg_response_time || 0) + 'h';
    }
    
    // Update leads change
    const leadsChangeEl = document.getElementById('totalLeadsChange');
    if (leadsChangeEl && stats.leads_change !== undefined) {
        const change = stats.leads_change;
        const sign = change >= 0 ? '+' : '';
        leadsChangeEl.textContent = sign + change + '% vs yesterday';
        leadsChangeEl.style.color = change >= 0 ? '#90EE90' : '#FFB6C1';
    }
    
    // Update qualified rate
    const qualifiedRateEl = document.getElementById('qualifiedRate');
    if (qualifiedRateEl && stats.conversion_rate !== undefined) {
        qualifiedRateEl.textContent = stats.conversion_rate + '% qualified';
    }
}

async function loadActivityFeed() {
    try {
        const response = await fetch('../includes/web_reports.php?endpoint=activity-feed&limit=10');
        const data = await response.json();
        
        if (data.success && data.data) {
            const feedEl = document.getElementById('activityFeed');
            if (feedEl) {
                if (data.data.length === 0) {
                    feedEl.innerHTML = '<div class="text-center text-muted">No recent activity</div>';
                } else {
                    feedEl.innerHTML = data.data.map(activity => {
                        const timeAgo = getTimeAgo(activity.timestamp);
                        const icon = activity.type === 'lead_assigned' ? 'fa-user-plus' : 'fa-comment';
                        const color = activity.type === 'lead_assigned' ? 'text-primary' : 'text-success';
                        return `
                            <div class="d-flex align-items-start mb-3 pb-3 border-bottom">
                                <i class="fas ${icon} ${color} me-2 mt-1"></i>
                                <div class="flex-grow-1">
                                    <div class="small fw-bold">${activity.message}</div>
                                    <div class="small text-muted">
                                        ${activity.lead_name ? 'Lead: ' + activity.lead_name : ''}
                                        ${activity.agent_name ? ' • Agent: ' + activity.agent_name : ''}
                                    </div>
                                    <div class="small text-muted">${timeAgo}</div>
                                </div>
                            </div>
                        `;
                    }).join('');
                }
            }
        }
    } catch (error) {
        console.error('Error loading activity feed:', error);
    }
}

function getTimeAgo(timestamp) {
    const now = new Date();
    const time = new Date(timestamp);
    const diff = Math.floor((now - time) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
    return Math.floor(diff / 86400) + ' days ago';
}

async function loadCampaignPerformance() {
    try {
        const response = await fetch('../includes/web_reports.php?endpoint=campaign-performance&period=month&limit=5');
        const data = await response.json();
        
        if (data.success && data.data) {
            createCampaignChart(data.data);
        }
    } catch (error) {
        console.error('Error loading campaign performance:', error);
    }
}

function createCampaignChart(campaignData) {
    const ctx = document.getElementById('campaignChart');
    if (!ctx) return;
    
    if (campaignChart) campaignChart.destroy();
    
    campaignChart = new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: campaignData.map(c => c.campaign_name || 'Unknown'),
            datasets: [
                {
                    label: 'Total Leads',
                    data: campaignData.map(c => c.total_leads),
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Qualified Leads',
                    data: campaignData.map(c => c.qualified_leads),
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Revenue (₹)',
                    data: campaignData.map(c => parseFloat(c.revenue)),
                    backgroundColor: 'rgba(255, 206, 86, 0.6)',
                    borderColor: 'rgba(255, 206, 86, 1)',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left'
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

async function loadCharts() {
    try {
        // Load status chart data
        const statusResponse = await fetch('../includes/web_reports.php?endpoint=dashboard');
        const statusData = await statusResponse.json();
        
        if (statusData.success && statusData.data.leads_by_status) {
            createStatusChart(statusData.data.leads_by_status);
        }
        
        // Load P&L chart data
        const pnlResponse = await fetch('../includes/web_reports.php?endpoint=pnl&period=week');
        const pnlData = await pnlResponse.json();
        
        if (pnlData.success) {
            createPnLChart(pnlData.data.data);
        }
    } catch (error) {
        console.error('Error loading charts:', error);
    }
}

function createStatusChart(statusData) {
    const ctx = document.getElementById('statusChart');
    if (!ctx) return;
    
    if (statusChart) statusChart.destroy();
    
    const labels = statusData.map(s => s.status.charAt(0).toUpperCase() + s.status.slice(1).replace('_', ' '));
    const data = statusData.map(s => s.count);
    const colors = ['#ffc107', '#17a2b8', '#28a745', '#dc3545', '#6c757d', '#007bff'];
    
    statusChart = new Chart(ctx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

function createPnLChart(pnlData) {
    const ctx = document.getElementById('pnlChart');
    if (!ctx) return;
    
    if (pnlChart) pnlChart.destroy();
    
    const labels = pnlData.map(d => new Date(d.date).toLocaleDateString());
    const revenue = pnlData.map(d => d.revenue);
    const expenses = pnlData.map(d => d.ad_spend + d.expenses);
    const profit = pnlData.map(d => d.profit);
    
    pnlChart = new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Revenue',
                    data: revenue,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Expenses',
                    data: expenses,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Profit',
                    data: profit,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₹' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ₹' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

async function loadTopPerformers() {
    try {
        const response = await fetch('../includes/web_reports.php?endpoint=top-performers&limit=5');
        const data = await response.json();
        
        if (data.success) {
            updateTopPerformersTable(data.data);
        }
    } catch (error) {
        console.error('Error loading top performers:', error);
    }
}

function updateTopPerformersTable(performers) {
    const tbody = document.getElementById('topPerformersTable');
    
    if (performers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No data available</td></tr>';
        return;
    }
    
    tbody.innerHTML = performers.map(p => `
        <tr>
            <td>${p.name}</td>
            <td>${p.total_leads}</td>
            <td>${p.qualified_leads}</td>
            <td>${p.conversion_rate}%</td>
            <td>₹${parseFloat(p.total_revenue).toLocaleString()}</td>
        </tr>
    `).join('');
}