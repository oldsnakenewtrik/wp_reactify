import React from 'react';

const Results = ({ results, onRestart }) => {
  const calculateTotal = () => {
    let total = 0;
    Object.values(results).forEach(category => {
      if (Array.isArray(category)) {
        category.forEach(item => total += item.price || 0);
      } else if (category.price) {
        total += category.price;
      }
    });
    return total;
  };

  return (
    <div className="p-6 bg-white rounded-lg shadow-lg">
      <h2 className="text-3xl font-bold text-center text-[#25385b] mb-6">
        Your SonderCare Bed Recommendation
      </h2>
      
      {Object.entries(results).map(([category, items]) => (
        <div key={category} className="mb-6">
          <h3 className="text-xl font-semibold text-[#25385b] mb-3">{category}</h3>
          {Array.isArray(items) ? (
            items.map((item, index) => (
              <div key={index} className="flex justify-between py-2 border-b">
                <span>{item.name}</span>
                <span className="font-semibold">${item.price.toLocaleString()}</span>
              </div>
            ))
          ) : (
            <div className="flex justify-between py-2 border-b">
              <span>{items.name}</span>
              <span className="font-semibold">${items.price.toLocaleString()}</span>
            </div>
          )}
        </div>
      ))}
      
      <div className="border-t-2 pt-4 mt-6">
        <div className="flex justify-between text-2xl font-bold text-[#25385b]">
          <span>Total:</span>
          <span>${calculateTotal().toLocaleString()}</span>
        </div>
      </div>
      
      <div className="mt-6 text-center">
        <button 
          onClick={onRestart}
          className="bg-[#25385b] text-white px-6 py-3 rounded-lg hover:bg-[#1e2d47] transition-colors"
        >
          Start New Selection
        </button>
      </div>
    </div>
  );
};

export default Results;