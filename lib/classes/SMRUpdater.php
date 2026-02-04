<?php

class SMRUpdater {
	public function updateSMRCharts()
	{
		try {
			// 1. Fetch New SMR Data (from Excel spreadsheet)
			$spreadsheetPath = '/path/to/your/smr_chart_data.xlsx';
			$newSMRChartData = $this->dataManager->readExcelData($spreadsheetPath);

			// 2. Process & Validate Data
			$processedData = $this->processSMRChartData($newSMRChartData);

			// 3. Identify New Entries
			$newEntries = $this->identifyNewSMRChartEntries($processedData);

			// 4. Insert New Entries
			foreach ($newEntries as $entry) {
				$this->dataManager->create('smr_chart', $entry);
			}

			echo "SMR Charts updated successfully.\n";

		} catch (\Exception $e) {
			// Handle exceptions gracefully
			echo 'An error occurred during SMR Charts update: ' . $e->getMessage() . "\n";
		}
	}

	protected function processSMRChartData($newSMRChartData)
	{
		$processedData = [];

		foreach ($newSMRChartData as $row => $rowData) {
			// Basic validation - ensure required fields are present
			if (!isset($rowData['Artists'], $rowData['Song'], $rowData['TW'])) {
				throw new \Exception('Missing required fields in row ' . ($row + 1));
			}

			// Data cleaning/transformation (adjust as needed)
			$rowData['Artists'] = implode(', ', $this->dataManager->getArtistsFromChartEntry($rowData)); // Assuming this method exists in DataManager
			$rowData['Date'] = date('Y-m-d', strtotime($rowData['Date'])); // Format date if necessary

			// More specific validations can be added here using the ChartValidator if needed

			$processedData[] = $rowData;
		}

		return $processedData;
	}

	protected function identifyNewSMRChartEntries($processedData)
	{
		$newEntries = [];

		foreach ($processedData as $entry) {
			// Check if an entry with the same 'Artists', 'Song', and 'Date' already exists
			$existingEntry = $this->dataManager->read('smr_chart', [
				'Artists' => $entry['Artists'],
				'Song' => $entry['Song'],
				'Date' => $entry['Date']
			]);

			if (!$existingEntry) {
				$newEntries[] = $entry;
			} else {
				// Update the existing entry with new data
				$this->dataManager->edit('smr_chart', $existingEntry['Id'], $entry);
			}
		}

		return $newEntries;
	}
}