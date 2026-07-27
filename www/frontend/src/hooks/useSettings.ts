import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { settingsService } from '../services/settings.service';
import type { UserSettings } from '../types/settings.types';

export function useSettings() {
  return useQuery({
    queryKey: ['settings'],
    queryFn: () => settingsService.get(),
  });
}

export function useSettingsUpdate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (settings: Partial<UserSettings>) => settingsService.update(settings),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['settings'] });
    },
  });
}
