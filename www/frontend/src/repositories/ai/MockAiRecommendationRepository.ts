import {

  AiRecommendationApiError,

  type AiRecommendationResult,

} from '@/types/aiRecommendation.types';

import type { AiRecommendationRepository } from './AiRecommendationRepository';



const MOCK_LATENCY_MS = 700;



function delay(ms: number): Promise<void> {

  return new Promise((resolve) => setTimeout(resolve, ms));

}



/**

 * Mock AI Recommendation — SSOT response shape; ready to swap for live API (Codex M4).

 * Flags (sessionStorage):

 * - `plustok_ai_recommend_fail=1` → AI_UNAVAILABLE

 * - `plustok_ai_recommend_malformed=1` → AI_ANALYSIS_MALFORMED

 * - `plustok_ai_recommend_low_conf=1` → confidence 0.45 (low-confidence badge)

 */

export class MockAiRecommendationRepository implements AiRecommendationRepository {

  async analyze(consultId: string): Promise<AiRecommendationResult> {

    await delay(MOCK_LATENCY_MS);



    if (typeof sessionStorage !== 'undefined') {

      if (sessionStorage.getItem('plustok_ai_recommend_fail') === '1') {

        throw new AiRecommendationApiError('AI_UNAVAILABLE', 'AI 분석을 완료할 수 없습니다', 503);

      }

      if (sessionStorage.getItem('plustok_ai_recommend_malformed') === '1') {

        throw new AiRecommendationApiError(

          'AI_ANALYSIS_MALFORMED',

          'AI 분석 결과를 해석할 수 없습니다',

          503,

        );

      }

    }



    const lowConf =

      typeof sessionStorage !== 'undefined' &&

      sessionStorage.getItem('plustok_ai_recommend_low_conf') === '1';



    return {

      consultId,

      sentiment: 'neutral',

      priority: 'high',

      tags: ['설치문의', '가격비교'],

      categoryAi: 'internet_install',

      contractScore: null,

      confidence: lowConf ? 0.45 : 0.82,

      analyzedAt: new Date().toISOString(),

    };

  }

}



export const mockAiRecommendationRepository = new MockAiRecommendationRepository();


