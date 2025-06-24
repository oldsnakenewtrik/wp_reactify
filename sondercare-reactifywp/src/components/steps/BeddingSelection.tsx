import React from 'react';

const BeddingSelection = ({ nextStep, prevStep, currentStep, totalSteps }) => {
  return (
    <div className="flex flex-col h-full">
      <div className="mb-4">
        <div className="flex justify-between items-center mb-2">
          <span className="text-sm text-gray-500">Step {currentStep} of {totalSteps}</span>
          <div className="w-32 bg-gray-200 rounded-full h-2">
            <div 
              className="bg-[#25385b] h-2 rounded-full transition-all duration-300"
              style={{ width: `${(currentStep / totalSteps) * 100}%` }}
            ></div>
          </div>
        </div>
      </div>

      <h2 className="text-2xl font-bold mb-6 text-center text-[#25385b]">
        Bedding Selection
      </h2>
      
      <div className="flex-grow flex items-center justify-center">
        <p className="text-gray-600">This step is being configured...</p>
      </div>

      <div className="flex justify-between mt-6">
        <button 
          onClick={prevStep}
          className="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition-colors"
        >
          Previous
        </button>
        <button 
          onClick={nextStep}
          className="bg-[#25385b] text-white px-6 py-3 rounded-lg hover:bg-[#1e2d47] transition-colors"
        >
          {currentStep === totalSteps ? 'Complete' : 'Next Step'}
        </button>
      </div>
    </div>
  );
};

export default BeddingSelection;