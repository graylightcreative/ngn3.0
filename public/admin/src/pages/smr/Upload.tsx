import { useState } from 'react'
import { Upload, CheckCircle, AlertCircle, Loader } from 'lucide-react'
import { uploadSMRFile } from '../../services/smrService'

export default function SMRUpload() {
  const [file, setFile] = useState<File | null>(null)
  const [isDragging, setIsDragging] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [success, setSuccess] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [uploadResult, setUploadResult] = useState<any>(null)

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault()
    setIsDragging(true)
  }

  const handleDragLeave = () => {
    setIsDragging(false)
  }

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault()
    setIsDragging(false)
    const droppedFile = e.dataTransfer.files[0]
    if (droppedFile) {
      setFile(droppedFile)
      setError(null)
    }
  }

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFile = e.target.files?.[0]
    if (selectedFile) {
      setFile(selectedFile)
      setError(null)
    }
  }

  const handleUpload = async () => {
    if (!file) {
      setError('Please select a file')
      return
    }

    setIsLoading(true)
    setSuccess(false)
    setError(null)

    try {
      const result = await uploadSMRFile(file)
      setUploadResult(result.data)
      setSuccess(true)
      setFile(null)

      // Reset after 3 seconds
      setTimeout(() => {
        setSuccess(false)
        setUploadResult(null)
      }, 3000)
    } catch (err: any) {
      setError(err.response?.data?.error || 'Upload failed')
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="max-w-4xl">
      {/* Upload Card */}
      <div className="card mb-6">
        <div className="flex items-center gap-3 mb-6">
          <Upload className="text-blue-500" size={28} />
          <h1 className="text-3xl font-bold text-gray-100">SMR Data Upload</h1>
        </div>

        <p className="text-gray-400 mb-6">
          Upload radio station data (Artist, Title, Spins, Adds). This is Erik's workflow for ingesting SMR data.
        </p>

        {/* File Drop Zone */}
        <div
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          onDrop={handleDrop}
          className={`border-2 border-dashed rounded-lg p-12 text-center transition ${
            isDragging
              ? 'border-brand-green bg-brand-green bg-opacity-5'
              : 'border-gray-600 hover:border-brand-green'
          } cursor-pointer`}
        >
          <input
            type="file"
            accept=".csv,.xls,.xlsx"
            onChange={handleFileSelect}
            className="hidden"
            id="file-input"
          />
          <label htmlFor="file-input" className="cursor-pointer">
            <Upload size={48} className="mx-auto text-gray-500 mb-3" />
            <p className="text-lg font-semibold text-gray-300 mb-2">
              {file ? file.name : 'Drop CSV/Excel file here'}
            </p>
            <p className="text-sm text-gray-500">or click to browse</p>
          </label>
        </div>

        {/* Upload Button */}
        {file && (
          <div className="mt-6 flex gap-3">
            <button
              onClick={handleUpload}
              disabled={isLoading}
              className={`btn-primary flex items-center gap-2 ${isLoading ? 'opacity-50 cursor-not-allowed' : ''}`}
            >
              {isLoading ? (
                <>
                  <Loader size={18} className="animate-spin" />
                  Uploading...
                </>
              ) : (
                'Upload'
              )}
            </button>
            <button
              onClick={() => {
                setFile(null)
                setError(null)
              }}
              className="btn-secondary"
            >
              Cancel
            </button>
          </div>
        )}

        {/* Error Message */}
        {error && (
          <div className="mt-6 flex gap-3 items-start p-4 bg-red-900 bg-opacity-20 border border-red-700 rounded-lg">
            <AlertCircle className="text-red-500 mt-1 flex-shrink-0" size={20} />
            <div>
              <p className="font-semibold text-red-400">Upload Error</p>
              <p className="text-sm text-red-300">{error}</p>
            </div>
          </div>
        )}

        {/* Success Message */}
        {success && uploadResult && (
          <div className="mt-6 flex gap-3 items-start p-4 bg-green-900 bg-opacity-20 border border-green-700 rounded-lg">
            <CheckCircle className="text-green-500 mt-1 flex-shrink-0" size={20} />
            <div>
              <p className="font-semibold text-green-400">Upload Successful</p>
              <p className="text-sm text-green-300 mt-1">
                Ingestion ID: {uploadResult.ingestion_id}
              </p>
              <p className="text-sm text-green-300">
                Records parsed: {uploadResult.records_parsed}
              </p>
              <p className="text-sm text-green-300 mt-2">
                Redirecting to review page...
              </p>
            </div>
          </div>
        )}
      </div>

      {/* Format Info */}
      <div className="card mb-6">
        <h3 className="font-semibold text-gray-100 mb-3">Expected File Format</h3>
        <p className="text-gray-400 text-sm mb-4">
          Your CSV or Excel file should have the following columns in order:
        </p>
        <div className="bg-gray-800 rounded-lg p-4">
          <table className="table-base text-sm">
            <thead>
              <tr>
                <th className="w-8">Col</th>
                <th>Column Name</th>
                <th>Required</th>
                <th>Example</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>1</td>
                <td>Artist Name</td>
                <td><span className="text-red-400">Yes</span></td>
                <td className="text-gray-400">The Beatles</td>
              </tr>
              <tr>
                <td>2</td>
                <td>Track Title</td>
                <td><span className="text-red-400">Yes</span></td>
                <td className="text-gray-400">Let It Be</td>
              </tr>
              <tr>
                <td>3</td>
                <td>Spin Count</td>
                <td><span className="text-red-400">Yes</span></td>
                <td className="text-gray-400">156</td>
              </tr>
              <tr>
                <td>4</td>
                <td>Add Count</td>
                <td><span className="text-gray-400">No</span></td>
                <td className="text-gray-400">8</td>
              </tr>
              <tr>
                <td>5</td>
                <td>ISRC Code</td>
                <td><span className="text-gray-400">No</span></td>
                <td className="text-gray-400">GBUM71505208</td>
              </tr>
              <tr>
                <td>6</td>
                <td>Station ID</td>
                <td><span className="text-gray-400">No</span></td>
                <td className="text-gray-400">42</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      {/* Info Box */}
      <div className="card border-blue-700 bg-blue-900 bg-opacity-20">
        <p className="text-sm text-blue-400">
          💡 After upload, you'll map artist identities to resolve unmatched names, then finalize the ingestion
          to commit the data to the chart.
        </p>
      </div>
    </div>
  )
}
