import { resolveDataSource } from '@/config/dataSource';

import { apiAiSummaryRepository } from './ApiAiSummaryRepository';
import { mockAiSummaryRepository } from './MockAiSummaryRepository';
import type { AiSummaryRepository } from './AiSummaryRepository';

import { apiAiRecommendationRepository } from './ApiAiRecommendationRepository';
import { mockAiRecommendationRepository } from './MockAiRecommendationRepository';
import type { AiRecommendationRepository } from './AiRecommendationRepository';

import { apiAiReplyRepository } from './ApiAiReplyRepository';
import { mockAiReplyRepository } from './MockAiReplyRepository';
import type { AiReplyRepository } from './AiReplyRepository';

export function createAiSummaryRepository(): AiSummaryRepository {
  return resolveDataSource() === 'api' ? apiAiSummaryRepository : mockAiSummaryRepository;
}

export function createAiRecommendationRepository(): AiRecommendationRepository {
  return resolveDataSource() === 'api'
    ? apiAiRecommendationRepository
    : mockAiRecommendationRepository;
}

export function createAiReplyRepository(): AiReplyRepository {
  return resolveDataSource() === 'api' ? apiAiReplyRepository : mockAiReplyRepository;
}

export type { AiSummaryRepository } from './AiSummaryRepository';
export { mockAiSummaryRepository } from './MockAiSummaryRepository';
export { apiAiSummaryRepository } from './ApiAiSummaryRepository';

export type { AiRecommendationRepository } from './AiRecommendationRepository';
export { mockAiRecommendationRepository } from './MockAiRecommendationRepository';
export { apiAiRecommendationRepository } from './ApiAiRecommendationRepository';

export type { AiReplyRepository } from './AiReplyRepository';
export { mockAiReplyRepository } from './MockAiReplyRepository';
export { apiAiReplyRepository } from './ApiAiReplyRepository';
