/**
 * Mock Google Trends API Implementation
 *
 * This file provides a mock implementation of Google Trends API functionality
 * for testing purposes. It returns predefined trend data based on the sample_data.json file.
 *
 * Usage:
 * 1. Import this file in your backend API implementation
 * 2. Use the provided functions instead of actual Google Trends API calls
 */

// Sample trend data - in a real implementation, this would be loaded from sample_data.json
const mockTrendData = [
  {
    "id": 1,
    "title": "Artificial Intelligence in Healthcare",
    "description": "The growing use of AI in medical diagnostics and treatment planning",
    "source": "google_trends",
    "niche": "tech",
    "popularity_score": 95,
    "related_keywords": ["AI diagnostics", "machine learning healthcare", "medical AI"],
    "metadata": {
      "search_volume": 250000,
      "growth_percentage": 35
    }
  },
  {
    "id": 2,
    "title": "Sustainable Fashion",
    "description": "Eco-friendly clothing brands and sustainable fashion practices",
    "source": "youtube",
    "niche": "lifestyle",
    "popularity_score": 87,
    "related_keywords": ["eco fashion", "sustainable clothing", "ethical fashion"],
    "metadata": {
      "video_count": 15000,
      "average_views": 120000
    }
  },
  {
    "id": 3,
    "title": "Home Workout Routines",
    "description": "Effective exercise routines that can be done at home with minimal equipment",
    "source": "google_trends",
    "niche": "fitness",
    "popularity_score": 92,
    "related_keywords": ["no equipment workout", "home fitness", "bodyweight exercises"],
    "metadata": {
      "search_volume": 320000,
      "growth_percentage": 28
    }
  },
  {
    "id": 4,
    "title": "Cryptocurrency Investments",
    "description": "Strategies for investing in Bitcoin and other cryptocurrencies",
    "source": "youtube",
    "niche": "finance",
    "popularity_score": 89,
    "related_keywords": ["bitcoin investing", "crypto portfolio", "ethereum"],
    "metadata": {
      "video_count": 22000,
      "average_views": 85000
    }
  },
  {
    "id": 5,
    "title": "Plant-Based Cooking",
    "description": "Vegan and vegetarian recipes and cooking techniques",
    "source": "google_trends",
    "niche": "food",
    "popularity_score": 84,
    "related_keywords": ["vegan recipes", "plant-based diet", "vegetarian cooking"],
    "metadata": {
      "search_volume": 180000,
      "growth_percentage": 22
    }
  }
];

/**
 * Get all trending topics
 * @param {Object} options - Filter and sort options
 * @param {number} options.limit - Maximum number of trends to return
 * @param {string} options.sort - Sort by 'popularity' or 'recent'
 * @returns {Promise<Array>} - Array of trending topics
 */
function getAllTrends({ limit = 10, sort = 'popularity' } = {}) {
  return new Promise((resolve) => {
    // Simulate API delay
    setTimeout(() => {
      let results = [...mockTrendData];

      // Sort results
      if (sort === 'popularity') {
        results.sort((a, b) => b.popularity_score - a.popularity_score);
      } else if (sort === 'recent') {
        // In a real implementation, this would sort by date
        // Here we just reverse the array to simulate different sorting
        results.reverse();
      }

      // Apply limit
      results = results.slice(0, limit);

      resolve(results);
    }, 300); // Simulate 300ms API delay
  });
}

/**
 * Get trending topics for a specific niche
 * @param {string} niche - The niche to filter by (e.g., 'tech', 'fitness')
 * @param {Object} options - Filter and sort options
 * @param {number} options.limit - Maximum number of trends to return
 * @param {string} options.sort - Sort by 'popularity' or 'recent'
 * @returns {Promise<Array>} - Array of trending topics for the specified niche
 */
function getTrendsByNiche(niche, { limit = 10, sort = 'popularity' } = {}) {
  return new Promise((resolve) => {
    // Simulate API delay
    setTimeout(() => {
      // Filter by niche
      let results = mockTrendData.filter(trend => trend.niche === niche);

      // Sort results
      if (sort === 'popularity') {
        results.sort((a, b) => b.popularity_score - a.popularity_score);
      } else if (sort === 'recent') {
        // In a real implementation, this would sort by date
        // Here we just reverse the array to simulate different sorting
        results.reverse();
      }

      // Apply limit
      results = results.slice(0, limit);

      resolve(results);
    }, 300); // Simulate 300ms API delay
  });
}

/**
 * Get related keywords for a specific trend
 * @param {number} trendId - The ID of the trend
 * @returns {Promise<Array>} - Array of related keywords
 */
function getRelatedKeywords(trendId) {
  return new Promise((resolve, reject) => {
    // Simulate API delay
    setTimeout(() => {
      const trend = mockTrendData.find(t => t.id === trendId);

      if (!trend) {
        reject(new Error(`Trend with ID ${trendId} not found`));
        return;
      }

      resolve(trend.related_keywords);
    }, 200); // Simulate 200ms API delay
  });
}

/**
 * Get trend metadata
 * @param {number} trendId - The ID of the trend
 * @returns {Promise<Object>} - Trend metadata
 */
function getTrendMetadata(trendId) {
  return new Promise((resolve, reject) => {
    // Simulate API delay
    setTimeout(() => {
      const trend = mockTrendData.find(t => t.id === trendId);

      if (!trend) {
        reject(new Error(`Trend with ID ${trendId} not found`));
        return;
      }

      resolve(trend.metadata);
    }, 200); // Simulate 200ms API delay
  });
}

module.exports = {
  getAllTrends,
  getTrendsByNiche,
  getRelatedKeywords,
  getTrendMetadata
};
