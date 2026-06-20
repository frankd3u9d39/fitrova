import { API_BASE_URL, endpoints } from './apiClient';
import { ChallengeParticipant, Challenge } from './dashboardService';

export interface ChatMessage {
  id: number;
  user_id: number;
  first_name: string;
  last_name: string;
  initials: string;
  color: string;
  profile_picture: string | null;
  message: string;
  created_at: string;
  parent_id?: number | null;
  parent_message?: string | null;
  parent_first_name?: string | null;
  parent_last_name?: string | null;
}

export interface ChallengeCommunityDetails {
  challenge: Challenge;
  participants: Array<ChallengeParticipant & {
    id: number;
    motto: string;
    connection_status: 'self' | 'not_connected' | 'pending_sent' | 'pending_received' | 'connected';
  }>;
}

const parseResponseJson = async (response: Response, endpointName: string) => {
  const text = await response.text();
  try {
    return JSON.parse(text);
  } catch (err) {
    console.error(`[communityService] Failed to parse JSON from ${endpointName}:`, text);
    throw new Error(`JSON Parse Error: Server returned non-JSON response: ${text.substring(0, 150)}...`);
  }
};

export const getChallengeDetails = async (userId: number, challengeKey: string): Promise<ChallengeCommunityDetails> => {
  const response = await fetch(endpoints.getChallengeDetails, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ user_id: userId, challenge_key: challengeKey }),
  });
  
  const result = await parseResponseJson(response, 'getChallengeDetails');
  if (result.status !== 'success') {
    throw new Error(result.message || 'Failed to load challenge details');
  }
  return result.data;
};

export const getChallengeMessages = async (challengeKey: string): Promise<ChatMessage[]> => {
  const response = await fetch(endpoints.getChallengeMessages, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'get', challenge_key: challengeKey }),
  });
  
  const result = await parseResponseJson(response, 'getChallengeMessages');
  if (result.status !== 'success') {
    throw new Error(result.message || 'Failed to load chat messages');
  }
  return result.messages;
};

export const sendChallengeMessage = async (userId: number, challengeKey: string, message: string, parentId?: number | null): Promise<void> => {
  const response = await fetch(endpoints.getChallengeMessages, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'send', user_id: userId, challenge_key: challengeKey, message, parent_id: parentId }),
  });
  
  const result = await parseResponseJson(response, 'sendChallengeMessage');
  if (result.status !== 'success') {
    throw new Error(result.message || 'Failed to send message');
  }
};

export const handleConnection = async (
  userId: number,
  targetId: number,
  action: 'send_request' | 'accept_request' | 'decline_request' | 'cancel_request'
): Promise<string> => {
  const response = await fetch(endpoints.manageConnections, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ user_id: userId, target_id: targetId, action }),
  });
  
  const result = await parseResponseJson(response, 'handleConnection');
  if (result.status !== 'success') {
    throw new Error(result.message || 'Failed to update connection');
  }
  return result.message;
};
