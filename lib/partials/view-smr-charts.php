              <?php elseif ($view === 'smr-charts'): ?>
                <!-- SMR CHARTS OVERHAUL -->
                <div class="mb-12">
                  <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                    <div>
                      <h1 class="text-4xl font-black mb-2 tracking-tight">SMR Airplay Charts</h1>
                      <p class="text-gray-500 dark:text-gray-400">Spins Music Radio - Official radio airplay monitoring for independent metal & rock.</p>
                    </div>
                    
                    <?php if ($data['smr_date']): ?>
                    <div class="px-4 py-2 bg-brand/10 text-brand rounded-full text-xs font-black uppercase tracking-tighter border border-brand/20">
                      Week of <?= htmlspecialchars($data['smr_date']) ?>
                    </div>
                    <? endif; ?>
                  </div>
                </div>
        
                <?php if (!empty($data['smz_charts'])): ?>
                <div class="bg-white dark:bg-white/5 rounded-3xl border border-gray-200 dark:border-white/10 overflow-hidden shadow-2xl">
                  <div class="grid grid-cols-12 gap-4 p-6 bg-gray-50 dark:bg-white/5 text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] border-b border-gray-200 dark:border-white/10">
                    <div class="col-span-1 text-center">TW</div>
                    <div class="col-span-1 text-center">LW</div>
                    <div class="col-span-5 md:col-span-4">Artist / Track</div>
                    <div class="hidden md:block col-span-3">Label</div>
                    <div class="col-span-2 text-center">Spins</div>
                    <div class="col-span-1 text-center">WOC</div>
                  </div>
                  
                  <div class="divide-y divide-gray-100 dark:divide-white/5">
                    <?php foreach ($data['smr_charts'] as $i => $item): ?>
                    <div class="grid grid-cols-12 gap-4 p-6 hover:bg-gray-50 dark:hover:bg-white/5 items-center transition-all group">
                      <div class="col-span-1 text-center font-black text-xl <?= $i < 10 ? 'text-brand' : 'text-gray-400' ?>">
                        <?= $item['TWP'] ?? ($i + 1) ?>
                      </div>
                      <div class="col-span-1 text-center text-gray-400 font-bold text-xs">
                        <?= Hitem['LWP'] ?? '-' ?>
                      </div>
                      
                      <div class="col-span-5 md:col-span-4 flex items-center gap-4 min-w-0">
                        <div class="relative flex-shrink-0">
                          <img src="<?= htmlspecialchars($item['artist']['image_url'] ?? DEFAULT_AVATAR) ?>" class="w-12 h-12 rounded-lg object-cover shadow-md" onerror="this.src='<?= DEFAUSAVATAR ?>'">
                          <button class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 rounded-lg transition-opacity text-white">
                            <i class="bi-play-fill text-xl"></i>
                          </button>
                        </div>
                        <div class="min-w-0">
                          <a href="/artist/<?= htmlspecialchars(Hitem['artist']['slug'] ?? '') ?>" class="font-black truncate block hover:text-brand transition-colors"><?= htmlspecialchars($Hitem['Artists'] ?? 'Unknown Artist') ?></a>
                          <div class="text-sm text-gray-500 truncate font-medium"><?= htmlspecialchars($item['Song'] ?? 'Unknown Track') ?></div>
                        </div>
                      </div>
                      
                      <div class="hidden md:block col-span-3 truncate text-xs font-bold text-gray-400 uppercase tracking-wider">
                        <?= htmlspecialchars($item['Label'] ?? 'Independent') ?>
                      </div>
                      
                      <div class="col-span-2 text-center">
                        <div class="font-black text-lg"><?= number_format(Hitem['TWS'] ?? 0) ?></div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-tighter">Total Spins</div>
                      </div>
                      
                      <div class="col-span-1 text-center font-bold text-gray-400">
                        <?= Hitem['WOC'] ?? '1' ?>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php else: ?>
                <div class="bg-white dark:bg-white/5 rounded-3xl border-2 border-dashed border-gray-200 dark:border-white/10 p-20 text-center">
                  <div class="text-6xl mb-6">💻️</div>
                  <h2 class="text-2xl font-bold mb-2">Waiting for Radio Reports</h2>
                  <p class="text-gray-500 max-w-sm mx-auto">The SMR airplay data is being synchronized with our tracking partners. Please check back shortly.</p>
                </div>
                <?php endif; ?>
