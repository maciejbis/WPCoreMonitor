// HookScanner Class
class HookScanner {
    constructor(container) {
        this.container = container;
        this.scanId = null;
        this.scanDir = '';
        this.scanType = 'plugin';
        this.totalBatches = 0;
        this.processedFiles = 0;
        this.totalFiles = 0;

        this.bindEvents();
    }

    bindEvents() {
        const controls = this.container.querySelector('#wcom-hooks-scanner-controls');
        controls?.addEventListener('submit', (e) => this.handleScanSubmit(e));
    }

    handleScanSubmit(e) {
        e.preventDefault();

        const form = e.target;
        this.container.querySelector('#start-scan').disabled = true;

        this.scanDir = form.querySelector('#dir-selector').value;
        this.scanType = form.querySelector('#dir-selector').selectedOptions[0].getAttribute('data-type');

        if (this.scanDir) {
            this.container.querySelector('.hooks-scanner-progress').style.display = 'none';
            this.processBatch(0, 0, 0, 0, this.scanDir);
            this.container.querySelector('.hooks-scanner-progress').style.display = 'block';
        } else {
            this.showError('Failed to scan the plugin files');
        }

        this.container.querySelector('.hooks-scanner-results').style.display = 'block';
    }

    async processBatch(batchIndex, totalBatches, processedCount = 0, totalCount = 0, scanDir = '') {
        if (scanDir) {
            this.resetScanState();
            this.updateProgress(0);
            this.container.querySelector('.results-content').innerHTML = '';
        } else if (batchIndex === totalBatches) {
            this.finishScan();
            return;
        }

        try {
            const response = await this.makeRequest({
                action: 'wcom_process_hooks_batch',
                _ajax_nonce: wpcoremonitor.nonce,
                scan_dir: scanDir,
                scan_type: this.scanType,
                scan_id: this.scanId,
                batch_index: batchIndex,
                processed_files: processedCount,
                total_files: totalCount
            });

            if (response.success) {
                this.updateScanState(response.data);

                if (Object.keys(response.data.found_hooks).length >= 1) {
                    const hooksHtml = this.createHookHtml(response.data.found_hooks);
                    this.container.querySelector('.results-content').insertAdjacentHTML('beforeend', hooksHtml);
                }

                const progress = (this.processedFiles / this.totalFiles) * 100;
                this.updateProgress(progress);

                this.processBatch(
                    batchIndex + 1,
                    this.totalBatches,
                    this.processedFiles,
                    this.totalFiles
                );
            } else {
                this.showError('Failed to process batch');
            }
        } catch (error) {
            this.showError('Failed to process batch: ' + error.message);
        }
    }

    async makeRequest(data) {
        const response = await fetch(wpcoremonitor.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    }

    resetScanState() {
        this.scanId = 0;
        this.totalBatches = 0;
        this.processedFiles = 0;
        this.totalFiles = 0;
    }

    updateScanState(data) {
        this.scanId = data.scan_id;
        this.totalBatches = data.total_batches;
        this.processedFiles = data.processed_files;
        this.totalFiles = data.total_files;
    }

    updateProgress(percentage) {
        const progressBar = this.container.querySelector('.progress-bar-fill');
        const progressText = this.container.querySelector('.progress-text');

        progressBar.style.width = `${percentage}%`;
        progressText.textContent = `${Math.round(percentage)}%`;
    }

    createHookHtml(foundHooks) {
        let html = '<div class="hooks">';

        for (const [key, hookData] of Object.entries(foundHooks)) {
            for (const [filePath, hookDetails] of Object.entries(hookData)) {
                const editURL = hookDetails.meta?.editURL ?? '#';

                html += `
          <div class="notice inline">
            <h3 class="filename">
              ${filePath} 
              <a href="${editURL}" target="_blank" class="button button-small button-secondary">
                Show in editor
              </a>
            </h3>
        `;

                if (hookDetails.actions?.length > 0) {
                    html += `
            <div>
              <p class="text-strong">Actions:</p>
              ${hookDetails.actions.map(action => `
                <pre><span>Line ${action.line}:</span><code>${action.content}</code></pre>
              `).join('')}
            </div>
          `;
                }

                if (hookDetails.filters?.length > 0) {
                    html += `
            <div>
              <p class="text-strong">Filters:</p>
              ${hookDetails.filters.map(filter => `
                <pre><span>Line ${filter.line}:</span><code>${filter.content}</code></pre>
              `).join('')}
            </div>
          `;
                }

                html += '</div>';
            }
        }

        return html + '</div>';
    }

    finishScan() {
        this.container.querySelector('#start-scan').disabled = false;
        this.container.querySelector('.hooks-scanner-progress').style.display = 'none';

        const resultsContent = this.container.querySelector('.results-content');
        if (resultsContent && !resultsContent.innerHTML.trim()) {
            resultsContent.innerHTML = '<div class="notice inline">No hooks found</div>';
        }
    }

    showError(message) {
        this.container.querySelector('.results-content').insertAdjacentHTML(
            'beforeend',
            `<div class="notice notice-error inline">${message}</div>`
        );
        this.finishScan();
    }
}

class TabManager {
    constructor(container) {
        this.container = container;
        this.tabs = container.querySelectorAll('.nav-tab');
        this.contents = container.querySelectorAll('.wcom-tab-content');

        this.bindEvents();
        this.initializeActiveTab();
    }

    bindEvents() {
        this.tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                const tabId = tab.dataset.tab;
                this.switchTab(tabId);
            });
        });
    }

    switchTab(tabId) {
        // Update tabs
        this.tabs.forEach(tab => {
            tab.classList.toggle('nav-tab-active', tab.dataset.tab === tabId);
        });

        // Update content
        this.contents.forEach(content => {
            content.style.display = content.id === tabId ? 'block' : 'none';
        });

        // Update URL
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabId);
        window.history.pushState({}, '', url);
    }

    initializeActiveTab() {
        const params = new URLSearchParams(window.location.search);
        const tabParam = params.get('tab');

        if (tabParam) {
            // If tab parameter exists in URL, use it
            this.switchTab(tabParam);
        } else {
            // If no tab parameter, use the first tab
            const firstTab = this.tabs[0]?.dataset.tab;
            if (firstTab) {
                this.switchTab(firstTab);
            }
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const hookScanner = new HookScanner(document.getElementById('wcom-hooks-scanner'));
    const tabManager = new TabManager(document.getElementById('wcom-tabs'));
});