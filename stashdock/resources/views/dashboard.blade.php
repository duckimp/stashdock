<x-app-layout>
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Page Title -->
        <h1 class="text-xl font-semibold text-gray-900 mb-6">Dashboard</h1>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">

            <!-- Total Projects -->
            <div class="bg-white rounded-xl border border-gray-200 px-6 py-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Projects</p>
                <p class="text-3xl font-bold text-gray-900">{{ $total }}</p>
            </div>

            <!-- Synced -->
            <div class="bg-white rounded-xl border border-gray-200 px-6 py-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Synced</p>
                <p class="text-3xl font-bold text-green-600">{{ $synced }}</p>
            </div>

            <!-- Need Attention -->
            <div class="bg-white rounded-xl border border-gray-200 px-6 py-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Need Attention</p>
                <p class="text-3xl font-bold text-orange-500">{{ $needAttention }}</p>
            </div>

        </div>

        <!-- Chart Area -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">

            <!-- Chart Header Row -->
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-gray-700">Weekly Activity</h2>
                <div class="flex items-center gap-2">
                    <!-- Sync Graph Button -->
                    <div x-data="syncGraph()">
                        <button
                            @click="sync()"
                            :disabled="syncing"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-200 rounded-md hover:bg-gray-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            title="Sync activity graph by scanning git log across all projects">
                            <span x-show="!syncing">🔄 Sync Graph</span>
                            <span x-show="syncing" x-cloak>Syncing…</span>
                        </button>
                    </div>

                    <!-- Clone New Project Button -->
                    <div x-data="cloneModal()">
                        <button
                            @click="open = true"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-700 transition-colors"
                            title="Clone a remote Git repository into your projects folder">
                            + Clone New Project
                        </button>

                        <!-- Clone Modal -->
                        <div x-show="open" x-cloak
                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
                             @keydown.escape.window="open = false">
                            <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6"
                                 @click.outside="open = false">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-base font-semibold text-gray-900">Clone Repository</h3>
                                    <button @click="open = false" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
                                </div>

                                <!-- Error / Success -->
                                <div x-show="error" class="mb-3 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700" x-text="error"></div>
                                <div x-show="success" class="mb-3 p-3 bg-green-50 border border-green-200 rounded-md text-sm text-green-700" x-text="success"></div>

                                <label class="block text-sm font-medium text-gray-700 mb-1">Repository URL</label>
                                <input
                                    type="url"
                                    x-model="url"
                                    placeholder="https://github.com/user/repo.git"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400 mb-4"
                                    @keydown.enter="clone()"
                                >

                                <div class="flex justify-end gap-2">
                                    <button @click="open = false"
                                            class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-200 rounded-md hover:bg-gray-100 transition-colors">
                                        Cancel
                                    </button>
                                    <button @click="clone()"
                                            :disabled="loading || !url.trim()"
                                            class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                        <span x-show="!loading">Clone</span>
                                        <span x-show="loading" x-cloak>Cloning…</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart Canvas -->
            <canvas id="activityChart" class="w-full" style="max-height: 280px;"></canvas>
        </div>

    </div>

    <!-- Chart.js Initialization -->
    <script>
        (function () {
            const chartData = @json($chartData);
            const ctx = document.getElementById('activityChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [
                        {
                            label: 'Commits',
                            data: chartData.commits,
                            borderColor: '#22c55e',
                            backgroundColor: 'rgba(34,197,94,0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Pushes',
                            data: chartData.pushes,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59,130,246,0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: {
                            min: 0,
                            ticks: { stepSize: 1, precision: 0 }
                        }
                    }
                }
            });
        })();
    </script>

    <!-- Alpine.js Components -->
    <script>
        document.addEventListener('alpine:init', () => {

            Alpine.data('syncGraph', () => ({
                syncing: false,
                async sync() {
                    this.syncing = true;
                    await fetch('/dashboard/sync-logs', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        }
                    });
                    this.syncing = false;
                    window.location.reload();
                }
            }));

            Alpine.data('cloneModal', () => ({
                open: false,
                url: '',
                loading: false,
                error: null,
                success: null,
                async clone() {
                    this.loading = true;
                    this.error = null;
                    this.success = null;
                    const res = await fetch('/clone', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ repo_url: this.url })
                    });
                    const data = await res.json();
                    this.loading = false;
                    if (data.status === 'ok') {
                        this.success = 'Repository cloned successfully!';
                        this.url = '';
                    } else {
                        this.error = data.message;
                    }
                }
            }));

        });
    </script>
</x-app-layout>
